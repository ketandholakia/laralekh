<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Company membership pivot. Users, tokens and permission definitions stay global;
// membership gates which companies a session may enter. Roles remain Spatie
// company-team rows, so access still needs a per-company role assignment.
return new class extends Migration {
    public function up(): void
    {
        Schema::create('company_user', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->timestamps();
            $t->unique(['company_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_user');
    }
};
