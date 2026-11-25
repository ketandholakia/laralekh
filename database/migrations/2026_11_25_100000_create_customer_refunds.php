<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Customer credit refunds: disbursement of excess credits and unapplied advances
// back to the customer (Dr customer receivable, Cr cash/bank). Allocations link
// the refund to unapplied receipt advances or negative invoice balances.
// History is immutable; reversals restore available credits/advances.
return new class extends Migration
{
    private function tenant(Blueprint $t, ?string $fkName = null): void
    {
        $t->id();
        $t->foreignId('company_id')->constrained()->restrictOnDelete();
        $t->unsignedBigInteger('financial_year_id');
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
            DB::unprepared("CREATE TRIGGER $name BEFORE $event ON $table WHEN $condition BEGIN SELECT RAISE(ABORT, 'Immutable customer refund record'); END");
        } elseif (DB::getDriverName() === 'mysql') {
            DB::unprepared("CREATE TRIGGER $name BEFORE $event ON $table FOR EACH ROW BEGIN IF $condition THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Immutable customer refund record'; END IF; END");
        } else {
            throw new RuntimeException('Customer refunds require MySQL or SQLite.');
        }
    }

    public function up(): void
    {
        Schema::create('customer_refunds', function (Blueprint $t) {
            $this->tenant($t, 'cust_refunds_tenant_fk');
            $t->unsignedBigInteger('party_id');
            $t->foreign(['company_id', 'party_id'], 'cust_refunds_party_fk')->references(['company_id', 'id'])->on('parties');
            $t->unsignedBigInteger('ledger_id');
            $t->foreign(['company_id', 'ledger_id'], 'cust_refunds_ledger_fk')->references(['company_id', 'id'])->on('ledgers');
            $t->unsignedBigInteger('voucher_id')->nullable()->unique();
            $t->foreign(['company_id', 'financial_year_id', 'voucher_id'], 'cust_refunds_voucher_fk')
                ->references(['company_id', 'financial_year_id', 'id'])->on('vouchers');
            $t->enum('status', ['draft', 'posted'])->default('draft');
            $t->string('series', 10)->default('CRF');
            $t->unsignedBigInteger('number')->nullable();
            $t->unique(['company_id', 'financial_year_id', 'series', 'number'], 'cust_refunds_series_num_uniq');
            $t->date('date');
            $t->string('payment_reference', 60)->nullable();
            $t->text('narration')->nullable();
            $this->money($t, 'amount');
            $t->json('party_snapshot')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('customer_refund_allocations', function (Blueprint $t) {
            $this->tenant($t, 'cust_refund_allocs_tenant_fk');
            $t->unsignedBigInteger('customer_refund_id');
            $t->foreign(['company_id', 'financial_year_id', 'customer_refund_id'], 'cust_refund_allocs_refund_fk')
                ->references(['company_id', 'financial_year_id', 'id'])->on('customer_refunds')->cascadeOnDelete();
            $t->enum('allocation_type', ['receipt', 'invoice']);
            $t->unsignedBigInteger('receipt_id')->nullable();
            $t->foreign('receipt_id', 'cust_refund_allocs_receipt_fk')
                ->references('id')->on('receipts')->nullOnDelete();
            $t->unsignedBigInteger('invoice_id')->nullable();
            $t->foreign('invoice_id', 'cust_refund_allocs_invoice_fk')
                ->references('id')->on('invoices')->nullOnDelete();
            $this->money($t, 'amount');
        });

        Schema::create('customer_refund_reversals', function (Blueprint $t) {
            $this->tenant($t, 'cust_refund_revs_tenant_fk');
            $t->unsignedBigInteger('original_financial_year_id');
            $t->unsignedBigInteger('original_customer_refund_id');
            $t->foreign(['company_id', 'original_financial_year_id', 'original_customer_refund_id'], 'cust_refund_revs_orig_fk')
                ->references(['company_id', 'financial_year_id', 'id'])->on('customer_refunds');
            $t->unique(['company_id', 'original_customer_refund_id'], 'cust_refund_revs_orig_uniq');
            $t->unsignedBigInteger('voucher_id')->nullable()->unique();
            $t->foreign(['company_id', 'financial_year_id', 'voucher_id'], 'cust_refund_revs_voucher_fk')
                ->references(['company_id', 'financial_year_id', 'id'])->on('vouchers');
            $t->enum('status', ['draft', 'posted'])->default('draft');
            $t->date('date');
            $t->text('reason');
            $t->json('original_snapshot');
            $t->unsignedBigInteger('created_by')->nullable();
            $t->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        foreach (['customer_refunds', 'customer_refund_reversals'] as $table) {
            $this->guard($table.'_u', $table, 'UPDATE', "OLD.status = 'posted'");
            $this->guard($table.'_d', $table, 'DELETE', "OLD.status = 'posted'");
        }
        foreach (['INSERT' => 'i', 'UPDATE' => 'u', 'DELETE' => 'd'] as $event => $suffix) {
            $conditions = [];
            foreach (($event === 'INSERT' ? ['NEW'] : ($event === 'DELETE' ? ['OLD'] : ['OLD', 'NEW'])) as $row) {
                $conditions[] = "EXISTS (SELECT 1 FROM customer_refunds WHERE id = $row.customer_refund_id AND status = 'posted')";
            }
            $this->guard('cust_refund_allocs_'.$suffix, 'customer_refund_allocations', $event, implode(' OR ', $conditions));
        }
    }

    public function down(): void
    {
        foreach (['customer_refunds', 'customer_refund_reversals'] as $table) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$table}_u");
            DB::unprepared("DROP TRIGGER IF EXISTS {$table}_d");
        }
        foreach (['i', 'u', 'd'] as $suffix) {
            DB::unprepared("DROP TRIGGER IF EXISTS cust_refund_allocs_$suffix");
        }

        Schema::dropIfExists('customer_refund_reversals');
        Schema::dropIfExists('customer_refund_allocations');
        Schema::dropIfExists('customer_refunds');
    }
};
