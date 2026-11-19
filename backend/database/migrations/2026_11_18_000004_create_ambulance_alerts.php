<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ambulance_alerts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('dispatch_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('route_version');
            $table->unsignedInteger('eta_s');
            $table->timestampTz('gps_captured_at');
            $table->timestampTz('fcm_accepted_at')->nullable();
            $table->timestampTz('foreground_received_at')->nullable();
            $table->timestampTz('opened_at')->nullable();
            $table->timestampTz('created_at');
            $table->unique(['dispatch_id', 'user_id']);
        });

        Schema::create('ambulance_alert_outbox', function (Blueprint $table) {
            $table->foreignUuid('alert_id')->primary()->constrained('ambulance_alerts')->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestampTz('available_at');
            $table->timestampTz('expires_at');
            $table->timestampTz('claimed_at')->nullable();
            $table->index(['status', 'available_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ambulance_alert_outbox');
        Schema::dropIfExists('ambulance_alerts');
    }
};
