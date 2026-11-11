<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emergency_stations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('installation_type', ['policia', 'bombero', 'hospital']);
            $table->double('latitude');
            $table->double('longitude');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emergency_stations');
    }
};
