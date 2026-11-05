<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    private function tenant(Blueprint $t): void
    {
        $t->id();
        $t->foreignId('company_id')->constrained()->restrictOnDelete();
        $t->unsignedBigInteger('financial_year_id');
        $t->foreign(['company_id', 'financial_year_id'])->references(['company_id', 'id'])->on('financial_years');
        $t->unique(['company_id', 'financial_year_id', 'id'], $t->getTable().'_tenant_year');
        $t->timestamps();
    }
    private function money(Blueprint $t, string $name): void
    {
        if (DB::getDriverName() === 'sqlite') { $t->string($name, 17)->default('0.00'); }
        else { $t->decimal($name, 15, 2)->default('0.00'); }
    }
    private function guard(string $name, string $table, string $event, string $condition): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER $name BEFORE $event ON $table WHEN $condition BEGIN SELECT RAISE(ABORT, 'Immutable correction document'); END");
        } elseif (DB::getDriverName() === 'mysql') {
            DB::unprepared("CREATE TRIGGER $name BEFORE $event ON $table FOR EACH ROW BEGIN IF $condition THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Immutable correction document'; END IF; END");
        } else { throw new RuntimeException('Corrections require MySQL or SQLite.'); }
    }
    public function up(): void
    {
        Schema::table('financial_years', fn (Blueprint $t) => $t->date('annual_return_filed_on')->nullable());
        Schema::table('invoice_items', fn (Blueprint $t) => $t->unique(['company_id', 'financial_year_id', 'invoice_id', 'id'], 'invoice_items_source_identity'));
        Schema::table('vouchers', fn (Blueprint $t) => $t->unique(['company_id', 'reverses_voucher_id'], 'vouchers_single_reversal'));
        Schema::create('sales_corrections', function (Blueprint $t) {
            $this->tenant($t);
            $t->unsignedBigInteger('original_financial_year_id');
            $t->unsignedBigInteger('invoice_id');
            $t->foreign(['company_id', 'original_financial_year_id', 'invoice_id'], 'corrections_source_fk')
                ->references(['company_id', 'financial_year_id', 'id'])->on('invoices');
            $t->unsignedBigInteger('voucher_id')->nullable()->unique();
            $t->foreign(['company_id', 'financial_year_id', 'voucher_id'], 'corrections_voucher_fk')
                ->references(['company_id', 'financial_year_id', 'id'])->on('vouchers');
            $t->unique(['company_id', 'financial_year_id', 'original_financial_year_id', 'invoice_id', 'id'], 'corrections_line_identity');
            $t->enum('kind', ['credit', 'debit']);
            $t->enum('treatment', ['gst', 'commercial']);
            $t->enum('status', ['draft', 'posted'])->default('draft');
            $t->string('series', 10);
            $t->unsignedBigInteger('number')->nullable();
            $t->unique(['company_id', 'financial_year_id', 'series', 'number'], 'corrections_number_unique');
            $t->uuid('request_key');
            $t->unique(['company_id', 'request_key'], 'corrections_request_unique');
            $t->date('date');
            $t->string('reason_code', 40);
            $t->text('reason');
            $t->boolean('eligibility_confirmed')->default(false);
            $t->text('eligibility_evidence')->nullable();
            $t->date('reporting_date')->nullable();
            $t->date('annual_return_filed_on')->nullable();
            $t->text('delivery_address')->nullable();
            $t->json('supplier_snapshot');
            $t->json('party_snapshot');
            $t->json('original_snapshot');
            foreach (['taxable_amount', 'cgst', 'sgst', 'utgst', 'igst', 'recipient_tax', 'total'] as $field) { $this->money($t, $field); }
        });
        Schema::create('sales_correction_lines', function (Blueprint $t) {
            $this->tenant($t);
            $t->unsignedBigInteger('sales_correction_id');
            $t->unsignedBigInteger('original_financial_year_id');
            $t->unsignedBigInteger('invoice_id');
            $t->unsignedBigInteger('invoice_item_id');
            $t->foreign(['company_id', 'financial_year_id', 'original_financial_year_id', 'invoice_id', 'sales_correction_id'], 'correction_lines_parent_fk')
                ->references(['company_id', 'financial_year_id', 'original_financial_year_id', 'invoice_id', 'id'])->on('sales_corrections');
            $t->foreign(['company_id', 'original_financial_year_id', 'invoice_id', 'invoice_item_id'], 'correction_lines_source_fk')
                ->references(['company_id', 'financial_year_id', 'invoice_id', 'id'])->on('invoice_items');
            $t->unsignedInteger('position');
            $t->unique(['sales_correction_id', 'invoice_item_id'], 'correction_lines_item_unique');
            $t->string('description');
            $t->string('hsn_sac', 8);
            $t->decimal('quantity', 15, 3)->default('0.000');
            $t->decimal('rate', 6, 3);
            $t->unsignedBigInteger('sales_ledger_id');
            $t->foreign(['company_id', 'sales_ledger_id'], 'correction_lines_ledger_fk')->references(['company_id', 'id'])->on('ledgers');
            foreach (['taxable_amount', 'cgst', 'sgst', 'utgst', 'igst', 'recipient_tax', 'total',
                'basis_cgst', 'basis_sgst', 'basis_utgst', 'basis_igst', 'basis_recipient_tax'] as $field) { $this->money($t, $field); }
        });
        Schema::create('voucher_reversals', function (Blueprint $t) {
            $this->tenant($t);
            $t->unsignedBigInteger('original_financial_year_id');
            $t->unsignedBigInteger('original_voucher_id');
            $t->foreign(['company_id', 'original_financial_year_id', 'original_voucher_id'], 'reversals_source_fk')
                ->references(['company_id', 'financial_year_id', 'id'])->on('vouchers');
            $t->unique(['company_id', 'original_voucher_id'], 'reversals_source_unique');
            $t->unsignedBigInteger('voucher_id')->unique();
            $t->foreign(['company_id', 'financial_year_id', 'voucher_id'], 'reversals_voucher_fk')
                ->references(['company_id', 'financial_year_id', 'id'])->on('vouchers');
            $t->enum('status', ['draft', 'posted'])->default('draft');
            $t->date('date');
            $t->text('reason');
            $t->json('original_snapshot');
            $t->json('entries_snapshot');
        });
        foreach (['sales_corrections', 'voucher_reversals'] as $table) {
            $this->guard($table.'_immutable_u', $table, 'UPDATE', "OLD.status = 'posted'");
            $this->guard($table.'_immutable_d', $table, 'DELETE', "OLD.status = 'posted'");
            $this->guard($table.'_immutable_i', $table, 'INSERT', "NEW.status = 'posted'");
        }
        foreach (['INSERT' => 'i', 'UPDATE' => 'u', 'DELETE' => 'd'] as $event => $suffix) {
            $conditions = [];
            foreach (($event === 'INSERT' ? ['NEW'] : ($event === 'DELETE' ? ['OLD'] : ['OLD', 'NEW'])) as $row) {
                $conditions[] = "EXISTS (SELECT 1 FROM sales_corrections WHERE id = $row.sales_correction_id AND status = 'posted')";
            }
            $this->guard('correction_lines_immutable_'.$suffix, 'sales_correction_lines', $event, implode(' OR ', $conditions));
        }
    }
    public function down(): void
    {
        foreach (['sales_corrections_immutable', 'voucher_reversals_immutable', 'correction_lines_immutable'] as $prefix) {
            foreach (['i', 'u', 'd'] as $suffix) { DB::unprepared("DROP TRIGGER IF EXISTS {$prefix}_$suffix"); }
        }
        Schema::dropIfExists('voucher_reversals');
        Schema::dropIfExists('sales_correction_lines');
        Schema::dropIfExists('sales_corrections');
        Schema::table('vouchers', fn (Blueprint $t) => $t->dropUnique('vouchers_single_reversal'));
        Schema::table('invoice_items', fn (Blueprint $t) => $t->dropUnique('invoice_items_source_identity'));
        Schema::table('financial_years', fn (Blueprint $t) => $t->dropColumn('annual_return_filed_on'));
    }
};

