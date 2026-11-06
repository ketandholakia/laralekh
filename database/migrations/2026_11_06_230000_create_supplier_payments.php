<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
return new class extends Migration {
    private function money(Blueprint $t, string $name): void { DB::getDriverName() === 'sqlite' ? $t->string($name,17)->default('0.00') : $t->decimal($name,15,2)->default('0.00'); }
    public function up(): void {
        Schema::create('supplier_payments', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained()->restrictOnDelete(); $t->unsignedBigInteger('financial_year_id');
            $t->foreign(['company_id','financial_year_id'])->references(['company_id','id'])->on('financial_years')->restrictOnDelete();
            $t->unsignedBigInteger('party_id'); $t->foreign(['company_id','party_id'])->references(['company_id','id'])->on('parties')->restrictOnDelete();
            $t->unsignedBigInteger('ledger_id'); $t->foreign(['company_id','ledger_id'])->references(['company_id','id'])->on('ledgers')->restrictOnDelete();
            $t->unsignedBigInteger('voucher_id')->nullable(); $t->foreign(['company_id','financial_year_id','voucher_id'])->references(['company_id','financial_year_id','id'])->on('vouchers')->restrictOnDelete();
            $t->date('date'); $t->string('series',10)->default('SP'); $t->unsignedBigInteger('number')->nullable();
            $t->enum('status',['draft','posted'])->default('draft'); $this->money($t,'amount'); $t->json('supplier_snapshot')->nullable(); $t->timestamps();
            $t->unique(['company_id','financial_year_id','series','number']);
        });
        Schema::create('supplier_payment_allocations', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained()->restrictOnDelete(); $t->unsignedBigInteger('financial_year_id');
            $t->foreign(['company_id','financial_year_id'])->references(['company_id','id'])->on('financial_years')->restrictOnDelete();
            $t->unsignedBigInteger('payment_id'); $t->foreign(['company_id','financial_year_id','payment_id'])->references(['company_id','financial_year_id','id'])->on('supplier_payments')->restrictOnDelete();
            $t->unsignedBigInteger('bill_financial_year_id'); $t->unsignedBigInteger('purchase_bill_id');
            $t->foreign(['company_id','bill_financial_year_id','purchase_bill_id'])->references(['company_id','financial_year_id','id'])->on('purchase_bills')->restrictOnDelete();
            $this->money($t,'amount'); $t->timestamps(); $t->unique(['company_id','id']);
        });
        Schema::create('supplier_payment_allocation_reversals', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained()->restrictOnDelete(); $t->unsignedBigInteger('financial_year_id');
            $t->foreign(['company_id','financial_year_id'])->references(['company_id','id'])->on('financial_years')->restrictOnDelete();
            $t->unsignedBigInteger('allocation_id')->unique(); $t->foreign(['company_id','allocation_id'])->references(['company_id','id'])->on('supplier_payment_allocations')->restrictOnDelete();
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('supplier_payment_allocation_reversals'); Schema::dropIfExists('supplier_payment_allocations'); Schema::dropIfExists('supplier_payments'); }
};
