<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HU-26: línea de tiempo del caso. Cada cambio importante de un reporte
 * (recibido, verificado, unidad asignada, en camino, completado...) queda
 * registrado con su hora para que el ciudadano pueda seguirlo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('reports')->cascadeOnDelete();
            $table->foreignId('dispatch_id')->nullable()->constrained('dispatches')->nullOnDelete();
            $table->string('type', 30);
            $table->string('detail')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['report_id', 'id']);
        });

        Schema::table('dispatches', function (Blueprint $table) {
            // Momento en que la unidad salió hacia el incidente («en camino»)
            $table->timestamp('departed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('dispatches', function (Blueprint $table) {
            $table->dropColumn('departed_at');
        });
        Schema::dropIfExists('case_events');
    }
};
