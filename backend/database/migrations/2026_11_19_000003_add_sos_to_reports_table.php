<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HU-24 Botón de pánico: las alertas SOS se marcan como prioritarias.
 * Su tipo de reporte («Botón de pánico (SOS)») lo crea SosService la primera vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->boolean('is_sos')->default(false);
            $table->index(['is_sos', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropIndex(['is_sos', 'status']);
            $table->dropColumn('is_sos');
        });
    }
};
