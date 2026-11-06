<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function guard(string $name, string $table, string $event, string $condition): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER {$name} BEFORE {$event} ON {$table} WHEN {$condition} BEGIN SELECT RAISE(ABORT,'Invalid or immutable cancelled accounting document'); END");
        } elseif (DB::getDriverName() === 'mysql') {
            DB::unprepared("CREATE TRIGGER {$name} BEFORE {$event} ON {$table} FOR EACH ROW BEGIN IF {$condition} THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Invalid or immutable cancelled accounting document'; END IF; END");
        } else {
            throw new RuntimeException('Document cancellation requires MySQL or SQLite.');
        }
    }

    private function differences(array $columns): string
    {
        return implode(' OR ', array_map(fn ($column) => DB::getDriverName() === 'sqlite'
            ? "NOT (NEW.{$column} IS OLD.{$column})" : "NOT (NEW.{$column} <=> OLD.{$column})", $columns));
    }

    private function makeCancellationGuard(string $table, array $columns): void
    {
        $different = $this->differences($columns);
        $length = DB::getDriverName() === 'sqlite' ? 'length' : 'CHAR_LENGTH';
        $badReason = "NEW.cancellation_reason IS NULL OR {$length}(TRIM(NEW.cancellation_reason)) < 5 OR {$length}(NEW.cancellation_reason) > 2000";
        $postedValid = "NEW.status='cancelled' AND NEW.cancelled_at IS NOT NULL AND NOT ({$badReason}) AND OLD.voucher_id IS NOT NULL AND EXISTS (SELECT 1 FROM vouchers rv WHERE rv.company_id=OLD.company_id AND rv.reverses_voucher_id=OLD.voucher_id AND rv.status='posted') AND NOT ({$different})";
        $draftValid = "NEW.status='cancelled' AND NEW.cancelled_at IS NOT NULL AND NOT ({$badReason}) AND NEW.voucher_id IS NULL AND NEW.number IS NULL AND NOT ({$different})";
        $condition = "OLD.status='cancelled' OR (OLD.status='posted' AND NOT ({$postedValid})) OR (OLD.status='draft' AND NEW.status='cancelled' AND NOT ({$draftValid})) OR (OLD.status='draft' AND NEW.status!='cancelled' AND (NEW.cancelled_at IS NOT NULL OR NEW.cancellation_reason IS NOT NULL))";
        $this->guard($table.'_cancellation_update', $table, 'UPDATE', $condition);
        $this->guard($table.'_cancellation_delete', $table, 'DELETE', "OLD.status IN ('posted','cancelled')");
        $this->guard($table.'_cancellation_insert', $table, 'INSERT', "NEW.status='cancelled'");
    }

    private function protectLines(string $lineTable, string $parentTable, string $foreignKey, string $prefix): void
    {
        foreach (['INSERT' => 'i', 'UPDATE' => 'u', 'DELETE' => 'd'] as $event => $suffix) {
            $rows = $event === 'INSERT' ? ['NEW'] : ($event === 'DELETE' ? ['OLD'] : ['OLD', 'NEW']);
            $checks = array_map(fn ($row) => "EXISTS (SELECT 1 FROM {$parentTable} p WHERE p.id={$row}.{$foreignKey} AND p.status IN ('posted','cancelled'))", $rows);
            $this->guard($prefix.'_'.$suffix, $lineTable, $event, implode(' OR ', $checks));
        }
    }

    private function restoreLegacyGuards(): void
    {
        foreach (['UPDATE' => 'update', 'DELETE' => 'delete', 'INSERT' => 'insert'] as $event => $suffix) {
            $this->guard('invoices_posted_'.$suffix, 'invoices', $event, $event === 'INSERT' ? "NEW.status='posted'" : "OLD.status='posted'");
        }
        $this->protectLegacyLines('invoice_items', 'invoices', 'invoice_id', 'invoice_items_posted');
        foreach (['UPDATE' => 'u', 'DELETE' => 'd'] as $event => $suffix) {
            $this->guard('purchase_bills_immutable_'.$suffix, 'purchase_bills', $event, "OLD.status='posted'");
        }
        foreach (['INSERT' => 'i', 'UPDATE' => 'u', 'DELETE' => 'd'] as $event => $suffix) {
            $rows = $event === 'INSERT' ? ['NEW'] : ($event === 'DELETE' ? ['OLD'] : ['OLD', 'NEW']);
            $checks = array_map(fn ($row) => "EXISTS (SELECT 1 FROM purchase_bills WHERE id={$row}.purchase_bill_id AND status='posted')", $rows);
            $this->guard('purchase_bill_items_immutable_'.$suffix, 'purchase_bill_items', $event, implode(' OR ', $checks));
        }
    }

    private function protectLegacyLines(string $lineTable, string $parentTable, string $foreignKey, string $prefix): void
    {
        foreach (['INSERT' => 'insert', 'UPDATE' => 'update', 'DELETE' => 'delete'] as $event => $suffix) {
            $rows = $event === 'INSERT' ? ['NEW'] : ($event === 'DELETE' ? ['OLD'] : ['OLD', 'NEW']);
            $checks = array_map(fn ($row) => "EXISTS (SELECT 1 FROM {$parentTable} WHERE id={$row}.{$foreignKey} AND status='posted')", $rows);
            $this->guard($prefix.'_'.$suffix, $lineTable, $event, implode(' OR ', $checks));
        }
    }

    public function up(): void
    {
        Schema::table('invoices', fn (Blueprint $t) => $t->timestamp('cancelled_at')->nullable());
        Schema::table('invoices', fn (Blueprint $t) => $t->text('cancellation_reason')->nullable());
        Schema::table('purchase_bills', fn (Blueprint $t) => $t->timestamp('cancelled_at')->nullable());
        Schema::table('purchase_bills', fn (Blueprint $t) => $t->text('cancellation_reason')->nullable());
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE invoices MODIFY status ENUM('draft','posted','cancelled') NOT NULL DEFAULT 'draft'");
            DB::statement("ALTER TABLE purchase_bills MODIFY status ENUM('draft','posted','cancelled') NOT NULL DEFAULT 'draft'");
        }
        foreach (['invoices_posted_update', 'invoices_posted_delete', 'invoices_posted_insert', 'invoice_items_posted_insert', 'invoice_items_posted_update', 'invoice_items_posted_delete'] as $name) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$name}");
        }
        foreach (['purchase_bills_immutable_u', 'purchase_bills_immutable_d', 'purchase_bill_items_immutable_i', 'purchase_bill_items_immutable_u', 'purchase_bill_items_immutable_d'] as $name) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$name}");
        }

        $invoiceColumns = ['company_id', 'financial_year_id', 'party_id', 'voucher_id', 'date', 'due_date', 'series', 'number', 'supply_type', 'place_of_supply',
            'zero_rated_mode', 'sez_authorized', 'zero_rated_eligible', 'reverse_charge', 'supplier_snapshot', 'party_snapshot', 'taxable_amount', 'cgst', 'sgst', 'utgst', 'igst', 'total', 'recipient_tax', 'cess', 'recipient_cess'];
        $purchaseColumns = ['company_id', 'financial_year_id', 'party_id', 'voucher_id', 'supplier_bill_number', 'supplier_bill_date', 'date', 'place_of_supply',
            'supply_type', 'reverse_charge', 'series', 'number', 'supplier_snapshot', 'taxable_amount', 'cgst', 'sgst', 'utgst', 'igst', 'cess', 'total'];
        $this->makeCancellationGuard('invoices', $invoiceColumns);
        $this->makeCancellationGuard('purchase_bills', $purchaseColumns);
        $this->protectLines('invoice_items', 'invoices', 'invoice_id', 'invoice_items_cancellation');
        $this->protectLines('purchase_bill_items', 'purchase_bills', 'purchase_bill_id', 'purchase_bill_items_cancellation');
    }

    public function down(): void
    {
        if (DB::table('invoices')->where('status', 'cancelled')->exists() || DB::table('purchase_bills')->where('status', 'cancelled')->exists()) {
            throw new RuntimeException('Cannot remove cancellation support while cancelled documents exist.');
        }
        foreach (['invoices_cancellation_update', 'invoices_cancellation_delete', 'invoices_cancellation_insert',
            'purchase_bills_cancellation_update', 'purchase_bills_cancellation_delete', 'purchase_bills_cancellation_insert',
            'invoice_items_cancellation_i', 'invoice_items_cancellation_u', 'invoice_items_cancellation_d',
            'purchase_bill_items_cancellation_i', 'purchase_bill_items_cancellation_u', 'purchase_bill_items_cancellation_d'] as $name) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$name}");
        }
        Schema::table('invoices', fn (Blueprint $t) => $t->dropColumn(['cancelled_at', 'cancellation_reason']));
        Schema::table('purchase_bills', fn (Blueprint $t) => $t->dropColumn(['cancelled_at', 'cancellation_reason']));
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE invoices MODIFY status ENUM('draft','posted') NOT NULL DEFAULT 'draft'");
            DB::statement("ALTER TABLE purchase_bills MODIFY status ENUM('draft','posted') NOT NULL DEFAULT 'draft'");
        }
        $this->restoreLegacyGuards();
    }
};
