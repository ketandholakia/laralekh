<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function money(Blueprint $table, string $column): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $table->string($column, 17)->default('0.00');
        } else {
            $table->decimal($column, 15, 2)->default('0.00');
        }
    }

    public function up(): void
    {
        Schema::table('tax_rates', fn (Blueprint $t) => $t->decimal('cess_rate', 6, 3)->default('0.000'));
        foreach (['invoice_items', 'invoices'] as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                $this->money($t, 'cess');
                if ($table === 'invoices') {
                    $this->money($t, 'recipient_cess');
                }
            });
        }
        foreach (['sales_corrections', 'sales_correction_lines'] as $table) {
            Schema::table($table, function (Blueprint $t) use ($table) {
                $this->money($t, 'cess');
                if ($table === 'sales_correction_lines') {
                    $this->money($t, 'basis_cess');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('invoices', fn (Blueprint $t) => $t->dropColumn(['cess', 'recipient_cess']));
        Schema::table('invoice_items', fn (Blueprint $t) => $t->dropColumn('cess'));
        Schema::table('sales_correction_lines', fn (Blueprint $t) => $t->dropColumn(['cess', 'basis_cess']));
        Schema::table('sales_corrections', fn (Blueprint $t) => $t->dropColumn('cess'));
        Schema::table('tax_rates', fn (Blueprint $t) => $t->dropColumn('cess_rate'));
    }
};
