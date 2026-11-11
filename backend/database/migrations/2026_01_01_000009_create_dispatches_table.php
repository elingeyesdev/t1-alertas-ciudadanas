<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispatches', function (Blueprint $table) {
            $table->id();
            $table->timestamp('recorded_at')->useCurrent();
            $table->enum('state', ['en_curso', 'completado', 'cancelado'])->default('en_curso');
            $table->foreignId('report_id')->constrained('reports')->cascadeOnDelete();
            $table->foreignId('destination_id')->constrained('emergency_stations')->restrictOnDelete();
            $table->foreignUuid('attended_by')->constrained('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispatches');
    }
};
