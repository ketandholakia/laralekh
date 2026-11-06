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
            DB::unprepared("CREATE TRIGGER $name BEFORE $event ON $table WHEN $condition BEGIN SELECT RAISE(ABORT, 'Immutable or invalid accounting document'); END");
        } elseif (DB::getDriverName() === 'mysql') {
            DB::unprepared("CREATE TRIGGER $name BEFORE $event ON $table FOR EACH ROW BEGIN IF $condition THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Immutable or invalid accounting document'; END IF; END");
        } else {
            throw new RuntimeException('This MVP supports MySQL 8 and SQLite tests; PostgreSQL triggers need a dedicated implementation.');
        }
    }

    public function up(): void
    {
        foreach (['invoices', 'invoice_items'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                if (DB::getDriverName() === 'sqlite') {
                    $t->string('recipient_tax', 17)->default('0.00');
                } else {
                    $t->decimal('recipient_tax', 15, 2)->default('0.00');
                }
            });
        }
        foreach (['vouchers', 'invoices'] as $table) {
            $this->guard($table.'_posted_update', $table, 'UPDATE', "OLD.status = 'posted'");
            $this->guard($table.'_posted_delete', $table, 'DELETE', "OLD.status = 'posted'");
            $this->guard($table.'_posted_insert', $table, 'INSERT', "NEW.status = 'posted'");
        }
        foreach (['voucher_lines' => ['vouchers', 'voucher_id'], 'invoice_items' => ['invoices', 'invoice_id']] as $table => [$parent, $key]) {
            foreach (['INSERT', 'UPDATE', 'DELETE'] as $event) {
                $conditions = [];
                if ($event !== 'INSERT') {
                    $conditions[] = "EXISTS (SELECT 1 FROM $parent WHERE id = OLD.$key AND status = 'posted')";
                }
                if ($event !== 'DELETE') {
                    $conditions[] = "EXISTS (SELECT 1 FROM $parent WHERE id = NEW.$key AND status = 'posted')";
                }
                $this->guard($table.'_posted_'.strtolower($event), $table, $event, implode(' OR ', $conditions));
            }
        }
        $debit = DB::getDriverName() === 'sqlite' ? "CAST(REPLACE(debit, '.', '') AS INTEGER)" : 'debit';
        $credit = DB::getDriverName() === 'sqlite' ? "CAST(REPLACE(credit, '.', '') AS INTEGER)" : 'credit';
        $this->guard('vouchers_balance', 'vouchers', 'UPDATE',
            "NEW.status = 'posted' AND ((SELECT COUNT(*) FROM voucher_lines WHERE voucher_id = NEW.id) < 2 OR
            (SELECT COALESCE(SUM($debit),0) - COALESCE(SUM($credit),0) FROM voucher_lines WHERE voucher_id = NEW.id) <> 0)");
        foreach (['INSERT', 'UPDATE'] as $event) {
            $this->guard('voucher_lines_amounts_'.strtolower($event), 'voucher_lines', $event,
                'CAST(NEW.debit AS DECIMAL(15,2)) < 0 OR CAST(NEW.credit AS DECIMAL(15,2)) < 0 OR
                (CAST(NEW.debit AS DECIMAL(15,2)) = 0 AND CAST(NEW.credit AS DECIMAL(15,2)) = 0) OR
                (CAST(NEW.debit AS DECIMAL(15,2)) > 0 AND CAST(NEW.credit AS DECIMAL(15,2)) > 0)');
        }
        $this->guard('activity_log_immutable_update', 'activity_log', 'UPDATE', '1 = 1');
        $this->guard('activity_log_immutable_delete', 'activity_log', 'DELETE', '1 = 1');
    }

    public function down(): void
    {
        foreach (['vouchers', 'invoices', 'voucher_lines', 'invoice_items'] as $table) {
            foreach (['insert', 'update', 'delete'] as $event) {
                DB::unprepared("DROP TRIGGER IF EXISTS {$table}_posted_$event");
            }
        }
        foreach (['vouchers_balance', 'voucher_lines_amounts_insert', 'voucher_lines_amounts_update', 'activity_log_immutable_update', 'activity_log_immutable_delete'] as $name) {
            DB::unprepared("DROP TRIGGER IF EXISTS $name");
        }
        foreach (['invoices', 'invoice_items'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('recipient_tax'));
        }
    }
};
