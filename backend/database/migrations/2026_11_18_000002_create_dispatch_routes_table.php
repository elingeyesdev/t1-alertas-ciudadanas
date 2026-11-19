<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispatch_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispatch_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('route_version');
            $table->enum('status', ['active', 'replaced', 'completed'])->default('active');
            $table->geometry('geometry', subtype: 'linestring', srid: 4326);
            $table->double('distance_m');
            $table->double('duration_s');
            $table->timestampsTz();
            $table->unique(['dispatch_id', 'route_version']);
            $table->spatialIndex('geometry');
        });

        DB::statement("CREATE UNIQUE INDEX dispatch_routes_one_active ON dispatch_routes (dispatch_id) WHERE status = 'active'");
    }

    public function down(): void
    {
        Schema::dropIfExists('dispatch_routes');
    }
};
