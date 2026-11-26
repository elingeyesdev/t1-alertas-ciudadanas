<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('missing_cases', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 10);
            $table->string('status', 12)->index();
            $table->string('name', 120);
            $table->string('place', 180);
            $table->date('missing_on');
            $table->text('description');
            $table->string('reference_phone', 16);
            $table->json('photos');
            $table->foreignUuid('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->double('latitude')->nullable();
            $table->double('longitude')->nullable();
            $table->unsignedInteger('radius_m')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('found_at')->nullable();
            $table->timestamps();
            $table->index(['kind', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('missing_cases');
    }
};
