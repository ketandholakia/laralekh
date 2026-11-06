<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function tenant(Blueprint $t, bool $transaction = false): void
    {
        $t->id();
        $t->foreignId('company_id')->constrained()->restrictOnDelete();
        $t->unique(['company_id', 'id']);
        if ($transaction) {
            $t->unsignedBigInteger('financial_year_id');
            $t->foreign(['company_id', 'financial_year_id'])->references(['company_id', 'id'])->on('financial_years')->restrictOnDelete();
            $t->unique(['company_id', 'financial_year_id', 'id']);
        }
        $t->timestamps();
    }

    private function reference(Blueprint $t, string $column, string $table, bool $nullable = false, bool $year = false, ?string $name = null): void
    {
        $t->unsignedBigInteger($column)->nullable($nullable);
        $local = $year ? ['company_id', 'financial_year_id', $column] : ['company_id', $column];
        $remote = $year ? ['company_id', 'financial_year_id', 'id'] : ['company_id', 'id'];
        $t->foreign($local, $name)->references($remote)->on($table)->restrictOnDelete();
    }

    private function money(Blueprint $t, string $name): void
    {
        // SQLite's NUMERIC affinity converts decimals into binary floats. Tests use text.
        if (DB::getDriverName() === 'sqlite') {
            $t->string($name, 17)->default('0.00');
        } else {
            $t->decimal($name, 15, 2)->default('0.00');
        }
    }

    private function totals(Blueprint $t): void
    {
        foreach (['taxable_amount', 'cgst', 'sgst', 'utgst', 'igst', 'total'] as $name) {
            $this->money($t, $name);
        }
    }

    public function up(): void
    {
        Schema::create('states', function (Blueprint $t) {
            $t->char('code', 2)->primary();
            $t->string('name');
            $t->boolean('is_active')->default(true);
            $t->boolean('uses_utgst')->default(false);
        });
        Schema::create('companies', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('gstin', 15)->nullable()->unique();
            $t->char('state_code', 2);
            $t->foreign('state_code')->references('code')->on('states');
            $t->enum('gst_regime', ['regular', 'composition', 'unregistered'])->default('regular');
            $t->text('address')->nullable();
            $t->timestamps();
        });
        Schema::create('financial_years', function (Blueprint $t) {
            $this->tenant($t);
            $t->string('name', 9);
            $t->date('starts_on');
            $t->date('ends_on');
            $t->boolean('is_closed')->default(false);
            $t->unique(['company_id', 'name']);
            $t->unique(['company_id', 'starts_on']);
        });
        Schema::create('ledger_groups', function (Blueprint $t) {
            $this->tenant($t);
            $t->string('code', 40);
            $t->string('name');
            $t->enum('nature', ['asset', 'liability', 'equity', 'income', 'expense']);
            $this->reference($t, 'parent_id', 'ledger_groups', true);
            $t->unique(['company_id', 'code']);
        });
        Schema::create('ledgers', function (Blueprint $t) {
            $this->tenant($t);
            $this->reference($t, 'ledger_group_id', 'ledger_groups');
            $t->string('code', 40);
            $t->string('name');
            $t->unique(['company_id', 'code']);
        });
        Schema::create('parties', function (Blueprint $t) {
            $this->tenant($t);
            $this->reference($t, 'ledger_id', 'ledgers');
            $t->string('name');
            $t->string('gstin', 15)->nullable();
            $t->char('state_code', 2)->nullable();
            $t->foreign('state_code')->references('code')->on('states');
            $t->text('address')->nullable();
            $t->enum('registration_type', ['regular', 'composition', 'unregistered', 'overseas'])->default('unregistered');
            $t->unique(['company_id', 'ledger_id']);
        });
        Schema::create('tax_rates', function (Blueprint $t) {
            $this->tenant($t);
            $t->string('code', 40);
            $t->decimal('rate', 6, 3);
            $t->date('effective_from');
            $t->date('effective_to')->nullable();
            $t->boolean('is_verified')->default(false);
            $t->text('legal_reference')->nullable();
            $t->unique(['company_id', 'code', 'effective_from']);
        });
        Schema::create('items', function (Blueprint $t) {
            $this->tenant($t);
            $t->string('sku', 60);
            $t->string('name');
            $t->string('hsn_sac', 8);
            $t->string('unit', 10)->default('NOS');
            $t->enum('kind', ['goods', 'service'])->default('goods');
            $t->boolean('tracks_stock')->default(false);
            $this->money($t, 'sale_price');
            $t->string('tax_rate_code', 40)->nullable();
            $t->unique(['company_id', 'sku']);
        });
        Schema::create('number_sequences', function (Blueprint $t) {
            $this->tenant($t, true);
            $t->string('series', 10);
            $t->unsignedBigInteger('last_number')->default(0);
            $t->unique(['company_id', 'financial_year_id', 'series'], 'number_sequences_series_unique');
        });
        Schema::create('vouchers', function (Blueprint $t) {
            $this->tenant($t, true);
            $t->enum('type', ['sales', 'purchase', 'journal', 'receipt', 'payment', 'credit_note', 'debit_note', 'reversal']);
            $t->date('date');
            $t->string('series', 10);
            $t->unsignedBigInteger('number');
            $t->string('status', 16)->default('draft');
            $t->timestamp('posted_at')->nullable();
            $this->reference($t, 'reverses_voucher_id', 'vouchers', true, false, 'vouchers_reverses_voucher_fk');
            $t->text('narration')->nullable();
            $t->unique(['company_id', 'financial_year_id', 'series', 'number'], 'vouchers_series_number_unique');
        });
        Schema::create('voucher_lines', function (Blueprint $t) {
            $this->tenant($t, true);
            $this->reference($t, 'voucher_id', 'vouchers', false, true);
            $this->reference($t, 'ledger_id', 'ledgers');
            $t->unsignedInteger('position');
            $this->money($t, 'debit');
            $this->money($t, 'credit');
            $t->text('narration')->nullable();
            $t->unique(['voucher_id', 'position']);
        });
        Schema::create('invoices', function (Blueprint $t) {
            $this->tenant($t, true);
            $this->reference($t, 'party_id', 'parties');
            $this->reference($t, 'voucher_id', 'vouchers', true, true);
            $t->unique('voucher_id');
            $t->date('date');
            $t->date('due_date')->nullable();
            $t->string('series', 10)->default('SI');
            $t->unsignedBigInteger('number')->nullable();
            $t->string('status', 16)->default('draft');
            $t->enum('supply_type', ['domestic', 'export', 'sez'])->default('domestic');
            $t->char('place_of_supply', 2);
            $t->enum('zero_rated_mode', ['none', 'lut', 'with_payment'])->default('none');
            $t->boolean('sez_authorized')->default(false);
            $t->boolean('zero_rated_eligible')->default(false);
            $t->boolean('reverse_charge')->default(false);
            $t->json('supplier_snapshot')->nullable();
            $t->json('party_snapshot')->nullable();
            $this->totals($t);
            $t->unique(['company_id', 'financial_year_id', 'series', 'number'], 'invoices_series_number_unique');
        });
        Schema::create('invoice_items', function (Blueprint $t) {
            $this->tenant($t, true);
            $this->reference($t, 'invoice_id', 'invoices', false, true);
            $this->reference($t, 'item_id', 'items');
            $this->reference($t, 'sales_ledger_id', 'ledgers');
            $this->reference($t, 'tax_rate_id', 'tax_rates', true);
            $t->unsignedInteger('position');
            $t->string('description');
            $t->string('hsn_sac', 8);
            $t->decimal('quantity', 15, 3);
            $this->money($t, 'unit_price');
            $this->money($t, 'discount');
            $t->decimal('rate', 6, 3)->default('0.000');
            $t->enum('tax_category', ['taxable', 'nil_rated', 'exempt', 'non_gst'])->default('taxable');
            $this->totals($t);
            $t->unique(['invoice_id', 'position']);
        });
        Schema::create('stock_movements', function (Blueprint $t) {
            $this->tenant($t, true);
            $this->reference($t, 'item_id', 'items');
            $this->reference($t, 'voucher_id', 'vouchers', false, true);
            $t->date('date');
            $t->decimal('quantity', 15, 3); // Signed: inward positive, outward negative.
            $this->money($t, 'unit_cost');
            $t->string('reference')->nullable();
        });
        Schema::create('activity_log', function (Blueprint $t) {
            $this->tenant($t, true);
            $t->string('log_name')->nullable()->index();
            $t->text('description');
            $t->nullableMorphs('subject');
            $t->nullableMorphs('causer');
            $t->json('properties')->nullable();
            // activitylog v5 records per-attribute changes here.
            $t->json('attribute_changes')->nullable();
            $t->string('event')->nullable();
            $t->uuid('batch_uuid')->nullable();
        });
    }

    public function down(): void
    {
        foreach (['activity_log', 'stock_movements', 'invoice_items', 'invoices', 'voucher_lines', 'vouchers',
            'number_sequences', 'items', 'tax_rates', 'parties', 'ledgers', 'ledger_groups', 'financial_years', 'companies', 'states'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
