<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->text('description');
            // Coordenadas WGS84 (las mismas que usaba PostGIS con SRID 4326)
            $table->double('latitude');
            $table->double('longitude');
            $table->integer('weight');
            $table->string('zone')->nullable();
            $table->boolean('verified')->default(false);
            $table->timestamp('expires_at');
            $table->enum('status', ['activo', 'resuelto', 'vencido', 'eliminado'])->default('activo');
            $table->foreignUuid('creator_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('report_type_id')->constrained('report_types')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('deleted_at');
            $table->index('expires_at');
            $table->index(['latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
