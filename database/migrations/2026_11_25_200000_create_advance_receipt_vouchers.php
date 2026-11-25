<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Statutory Advance Receipt Vouchers (CGST s. 31(3)(d), Rule 50):
// Issued upon receipt of advance payment for supply of services or when supply
// is undetermined. Provisos to Rule 50 mandate 18% rate fallback if rate is not
// determinable, and inter-state (IGST) treatment if nature of supply is not
// determinable. Posted vouchers are immutable.
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
            DB::unprepared("CREATE TRIGGER $name BEFORE $event ON $table WHEN $condition BEGIN SELECT RAISE(ABORT, 'Immutable advance receipt voucher record'); END");
        } elseif (DB::getDriverName() === 'mysql') {
            DB::unprepared("CREATE TRIGGER $name BEFORE $event ON $table FOR EACH ROW BEGIN IF $condition THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Immutable advance receipt voucher record'; END IF; END");
        } else {
            throw new RuntimeException('Advance receipt vouchers require MySQL or SQLite.');
        }
    }

    public function up(): void
    {
        Schema::create('advance_receipt_vouchers', function (Blueprint $t) {
            $this->tenant($t, 'arv_tenant_fk');
            $t->unsignedBigInteger('party_id');
            $t->foreign(['company_id', 'party_id'], 'arv_party_fk')->references(['company_id', 'id'])->on('parties');
            $t->unsignedBigInteger('receipt_id')->nullable();
            $t->foreign('receipt_id', 'arv_receipt_fk')->references('id')->on('receipts')->nullOnDelete();
            $t->string('series', 10)->default('ARV');
            $t->unsignedBigInteger('number')->nullable();
            $t->unique(['company_id', 'financial_year_id', 'series', 'number'], 'arv_number_unique');
            $t->date('date');
            $t->string('place_of_supply', 2);
            $t->enum('supply_type', ['services', 'goods'])->default('services');
            $t->string('description');
            $t->boolean('is_rate_determinable')->default(true);
            $t->boolean('is_interstate_determinable')->default(true);
            $t->decimal('tax_rate', 5, 2)->default('18.00');
            foreach (['gross_amount', 'taxable_amount', 'cgst', 'sgst', 'utgst', 'igst', 'cess'] as $col) {
                $this->money($t, $col);
            }
            $t->enum('status', ['draft', 'posted'])->default('draft');
            $t->string('payment_reference', 60)->nullable();
            $t->text('narration')->nullable();
            $t->json('party_snapshot')->nullable();
            $t->json('supplier_snapshot')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        $this->guard('advance_receipt_vouchers_u', 'advance_receipt_vouchers', 'UPDATE', "OLD.status = 'posted'");
        $this->guard('advance_receipt_vouchers_d', 'advance_receipt_vouchers', 'DELETE', "OLD.status = 'posted'");
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS advance_receipt_vouchers_u');
        DB::unprepared('DROP TRIGGER IF EXISTS advance_receipt_vouchers_d');

        Schema::dropIfExists('advance_receipt_vouchers');
    }
};
