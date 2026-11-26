<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('missing_case_contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('missing_case_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('message')->nullable();
            $table->json('photos');
            $table->timestamps();
            $table->index(['missing_case_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('missing_case_contributions');
    }
};
