<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** HU-32: avisos de salud, viaje o desastres naturales por zona (círculo) y con vigencia. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('category', 20);
            $table->string('title', 120);
            $table->text('message');
            $table->double('latitude');
            $table->double('longitude');
            $table->unsignedInteger('radius_m');
            $table->timestamp('expires_at')->index();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('notified_count')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_alerts');
    }
};
