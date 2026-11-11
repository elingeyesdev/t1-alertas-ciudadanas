<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone', 20)->unique();
            $table->string('password');
            // Se guarda un hash SHA-256 del refresh token (nunca el token en texto plano)
            $table->string('refresh_token')->nullable();
            $table->string('fcm_token')->nullable();
            // Última ubicación conocida del usuario (para notificaciones cercanas)
            $table->double('last_latitude')->nullable();
            $table->double('last_longitude')->nullable();
            $table->foreignId('role_id')->constrained('roles')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('fcm_token');
            $table->index(['last_latitude', 'last_longitude']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
