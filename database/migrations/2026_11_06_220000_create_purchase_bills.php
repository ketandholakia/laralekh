<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    private function tenant(Blueprint $t): void
    {
        $t->id(); $t->foreignId('company_id')->constrained()->restrictOnDelete();
        $t->unsignedBigInteger('financial_year_id');
        $t->foreign(['company_id', 'financial_year_id'])->references(['company_id', 'id'])->on('financial_years')->restrictOnDelete();
        $t->unique(['company_id', 'financial_year_id', 'id']); $t->timestamps();
    }
    private function money(Blueprint $t, string $name): void
    {
        if (DB::getDriverName() === 'sqlite') $t->string($name, 17)->default('0.00');
        else $t->decimal($name, 15, 2)->default('0.00');
    }
    private function reference(Blueprint $t, string $column, string $table, bool $nullable = false): void
    {
        $t->unsignedBigInteger($column)->nullable($nullable);
        $t->foreign(['company_id', $column])->references(['company_id', 'id'])->on($table)->restrictOnDelete();
    }
    public function up(): void
    {
        Schema::create('purchase_bills', function (Blueprint $t) {
            $this->tenant($t); $this->reference($t, 'party_id', 'parties');
            $t->unsignedBigInteger('voucher_id')->nullable();
            $t->foreign(['company_id', 'financial_year_id', 'voucher_id'])->references(['company_id', 'financial_year_id', 'id'])->on('vouchers')->restrictOnDelete();
            $t->string('supplier_bill_number', 80); $t->date('supplier_bill_date'); $t->date('date');
            $t->char('place_of_supply', 2); $t->enum('supply_type', ['domestic', 'sez'])->default('domestic');
            $t->boolean('reverse_charge')->default(false); $t->enum('status', ['draft', 'posted'])->default('draft');
            $t->string('series', 10)->default('PB'); $t->unsignedBigInteger('number')->nullable();
            $t->json('supplier_snapshot')->nullable();
            foreach (['taxable_amount', 'cgst', 'sgst', 'utgst', 'igst', 'cess', 'total'] as $field) $this->money($t, $field);
            $t->unique(['company_id', 'financial_year_id', 'party_id', 'supplier_bill_number'], 'purchase_supplier_bill_unique');
            $t->unique(['company_id', 'financial_year_id', 'series', 'number'], 'purchase_bill_number_unique');
        });
        Schema::create('purchase_bill_items', function (Blueprint $t) {
            $this->tenant($t);
            $t->unsignedBigInteger('purchase_bill_id');
            $t->foreign(['company_id', 'financial_year_id', 'purchase_bill_id'], 'purchase_bill_items_parent_fk')->references(['company_id', 'financial_year_id', 'id'])->on('purchase_bills')->restrictOnDelete();
            $this->reference($t, 'item_id', 'items', true); $this->reference($t, 'purchase_ledger_id', 'ledgers');
            $this->reference($t, 'tax_rate_id', 'tax_rates', true);
            $t->unsignedInteger('position'); $t->string('description'); $t->string('hsn_sac', 8)->default('');
            $t->decimal('quantity', 15, 3); $this->money($t, 'unit_price'); $this->money($t, 'discount');
            $t->decimal('rate', 6, 3)->default('0.000'); $t->enum('tax_category', ['taxable', 'nil_rated', 'exempt', 'non_gst'])->default('taxable');
            foreach (['taxable_amount', 'cgst', 'sgst', 'utgst', 'igst', 'cess', 'total'] as $field) $this->money($t, $field);
            $t->enum('itc_eligibility', ['eligible', 'blocked', 'ineligible_reversal_review'])->default('eligible');
            $t->text('itc_reason')->nullable(); $t->unique(['purchase_bill_id', 'position']);
        });
    }
    public function down(): void { Schema::dropIfExists('purchase_bill_items'); Schema::dropIfExists('purchase_bills'); }
};
