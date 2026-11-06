<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Optional supplier payment details for the UPI convenience QR printed on
// posted invoices. Distinct from the IRP e-invoice QR, which is out of scope.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $t) {
            $t->string('upi_id', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $t) {
            $t->dropColumn('upi_id');
        });
    }
};
