<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Customer settlements: receipts are posted accounting documents (Dr cash/bank,
// Cr receivable); allocations are immutable insert-only links between a posted
// receipt and customer invoices, and never create accounting entries. Undo and
// reversal are themselves immutable linked records, so settlement history can
// only grow. Invoice outstanding balances are derived, never stored on invoices.
return new class extends Migration
{
    private function tenant(Blueprint $t, ?string $fkName = null): void
    {
        $t->id();
        $t->foreignId('company_id')->constrained()->restrictOnDelete();
        $t->unsignedBigInteger('financial_year_id');
        // MySQL caps identifiers at 64 characters; long table names need an
        // explicit FK name because the Laravel default would exceed it.
        $t->foreign(['company_id', 'financial_year_id'], $fkName)->references(['company_id', 'id'])->on('financial_years');
        $t->unique(['company_id', 'financial_year_id', 'id'], $t->getTable().'_tenant_year');
        $t->timestamps();
    }

    private function money(Blueprint $t, string $name): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $t->string($name, 17)->default('0.00');
        } else {
            $t->decimal($name, 15, 2)->default('0.00');
        }
    }

    private function guard(string $name, string $table, string $event, string $condition): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER $name BEFORE $event ON $table WHEN $condition BEGIN SELECT RAISE(ABORT, 'Immutable settlement record'); END");
        } elseif (DB::getDriverName() === 'mysql') {
            DB::unprepared("CREATE TRIGGER $name BEFORE $event ON $table FOR EACH ROW BEGIN IF $condition THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Immutable settlement record'; END IF; END");
        } else {
            throw new RuntimeException('Settlements require MySQL or SQLite.');
        }
    }

    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $t) {
            $this->tenant($t);
            $t->unsignedBigInteger('party_id');
            $t->foreign(['company_id', 'party_id'], 'receipts_party_fk')->references(['company_id', 'id'])->on('parties');
            $t->unsignedBigInteger('ledger_id');
            $t->foreign(['company_id', 'ledger_id'], 'receipts_ledger_fk')->references(['company_id', 'id'])->on('ledgers');
            $t->unsignedBigInteger('voucher_id')->nullable()->unique();
            $t->foreign(['company_id', 'financial_year_id', 'voucher_id'], 'receipts_voucher_fk')
                ->references(['company_id', 'financial_year_id', 'id'])->on('vouchers');
            $t->enum('status', ['draft', 'posted'])->default('draft');
            $t->string('series', 10)->default('SR');
            $t->unsignedBigInteger('number')->nullable();
            $t->unique(['company_id', 'financial_year_id', 'series', 'number'], 'receipts_number_unique');
            $t->uuid('request_key');
            $t->unique(['company_id', 'request_key'], 'receipts_request_unique');
            $t->date('date');
            $t->string('payment_reference', 60)->nullable();
            $t->text('narration')->nullable();
            $this->money($t, 'amount');
            $t->json('party_snapshot')->nullable();
            $t->json('allocations_snapshot')->nullable(); // allocations made at posting, for the original PDF
        });
        Schema::create('receipt_allocations', function (Blueprint $t) {
            $this->tenant($t);
            $t->unsignedBigInteger('receipt_id');
            $t->foreign(['company_id', 'financial_year_id', 'receipt_id'], 'allocations_receipt_fk')
                ->references(['company_id', 'financial_year_id', 'id'])->on('receipts');
            // Allocations may settle invoices from earlier financial years of the
            // same company and customer, so the invoice carries its own year key.
            $t->unsignedBigInteger('invoice_financial_year_id');
            $t->unsignedBigInteger('invoice_id');
            $t->foreign(['company_id', 'invoice_financial_year_id', 'invoice_id'], 'allocations_invoice_fk')
                ->references(['company_id', 'financial_year_id', 'id'])->on('invoices');
            $this->money($t, 'amount');
            $t->unsignedBigInteger('created_by')->nullable();
            $t->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
        Schema::create('receipt_reversals', function (Blueprint $t) {
            $this->tenant($t);
            $t->unsignedBigInteger('original_financial_year_id');
            $t->unsignedBigInteger('original_receipt_id');
            $t->foreign(['company_id', 'original_financial_year_id', 'original_receipt_id'], 'receipt_reversals_source_fk')
                ->references(['company_id', 'financial_year_id', 'id'])->on('receipts');
            $t->unique(['company_id', 'original_receipt_id'], 'receipt_reversals_source_unique');
            $t->unsignedBigInteger('voucher_id')->nullable()->unique();
            $t->foreign(['company_id', 'financial_year_id', 'voucher_id'], 'receipt_reversals_voucher_fk')
                ->references(['company_id', 'financial_year_id', 'id'])->on('vouchers');
            $t->enum('status', ['draft', 'posted'])->default('draft');
            $t->date('date');
            $t->text('reason');
            $t->json('original_snapshot');
            $t->json('undone_allocations')->nullable();
        });
        Schema::create('receipt_allocation_reversals', function (Blueprint $t) {
            $this->tenant($t, 'receipt_alloc_revs_tenant_fk');
            $t->unsignedBigInteger('allocation_id');
            $t->foreign(['company_id', 'financial_year_id', 'allocation_id'], 'allocation_reversals_source_fk')
                ->references(['company_id', 'financial_year_id', 'id'])->on('receipt_allocations');
            $t->unique(['company_id', 'allocation_id'], 'allocation_reversals_source_unique');
            $t->unsignedBigInteger('created_by')->nullable();
            $t->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
        // Posted receipts and reversals are immutable; allocations and their
        // undo records are insert-only, so history can never be rewritten.
        foreach (['receipts', 'receipt_reversals'] as $table) {
            $this->guard($table.'_settlement_u', $table, 'UPDATE', "OLD.status = 'posted'");
            $this->guard($table.'_settlement_d', $table, 'DELETE', "OLD.status = 'posted'");
            $this->guard($table.'_settlement_i', $table, 'INSERT', "NEW.status = 'posted'");
        }
        foreach (['receipt_allocations', 'receipt_allocation_reversals'] as $table) {
            $this->guard($table.'_append_only_u', $table, 'UPDATE', '1 = 1');
            $this->guard($table.'_append_only_d', $table, 'DELETE', '1 = 1');
        }
    }

    public function down(): void
    {
        foreach (['receipts', 'receipt_reversals', 'receipt_allocations', 'receipt_allocation_reversals'] as $prefix) {
            foreach (['settlement_u', 'settlement_d', 'settlement_i', 'append_only_u', 'append_only_d'] as $suffix) {
                DB::unprepared("DROP TRIGGER IF EXISTS {$prefix}_{$suffix}");
            }
        }
        Schema::dropIfExists('receipt_allocation_reversals');
        Schema::dropIfExists('receipt_reversals');
        Schema::dropIfExists('receipt_allocations');
        Schema::dropIfExists('receipts');
    }
};
