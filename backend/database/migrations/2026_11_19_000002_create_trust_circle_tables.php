<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HU-25 Círculo de confianza (estilo Life360).
 * - trust_contacts: hasta 5 contactos por persona. Con la app (contact_user_id)
 *   reciben push; sin la app solo nombre y teléfono (aviso por WhatsApp).
 * - trust_circle_invites: códigos de invitación de un solo uso.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trust_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('contact_user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('phone', 20);
            $table->timestamps();

            $table->index('owner_id');
            $table->unique(['owner_id', 'contact_user_id']);
        });

        Schema::create('trust_circle_invites', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('code', 12)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->foreignUuid('used_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trust_circle_invites');
        Schema::dropIfExists('trust_contacts');
    }
};
