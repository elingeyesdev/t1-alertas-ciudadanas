<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ambulance_alert_locations', function (Blueprint $table) {
            $table->foreignUuid('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->geography('position', subtype: 'point', srid: 4326)->nullable();
            $table->double('accuracy_m')->nullable();
            $table->timestampTz('captured_at')->nullable()->index();
        });

        DB::statement('CREATE INDEX ambulance_alert_locations_position_gist ON ambulance_alert_locations USING GiST (position)');
    }

    public function down(): void
    {
        Schema::dropIfExists('ambulance_alert_locations');
    }
};
