<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authority_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('ci');
            $table->string('gmail');
            $table->enum('profile_type', ['policia', 'bombero', 'paramedico']);
            // Relación 1 a 1 con users
            $table->foreignUuid('user_id')->unique()->constrained('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authority_profiles');
    }
};
