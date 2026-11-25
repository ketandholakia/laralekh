<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// GSTR-2B Inward Supply ITC Statement Imports:
// Stores parsed portal GSTR-2B data (imported via official GST Portal JSON or
// standard B2B CSV) and holds the cached reconciliation status against booked
// purchase bills for the company and return period.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gstr2b_imports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->char('period', 7);
            $t->string('source_filename');
            $t->enum('file_format', ['json', 'csv']);
            $t->string('gstin', 15)->nullable();
            $t->unsignedInteger('total_documents')->default(0);
            $t->json('summary')->nullable();
            $t->json('records')->nullable();
            $t->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('imported_at')->useCurrent();
            $t->timestamps();

            $t->unique(['company_id', 'period'], 'gstr2b_imports_company_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gstr2b_imports');
    }
};
