<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->string('text', 250);
            $table->timestamp('created_at')->useCurrent();
            // Auto-referencia: un comentario puede ser respuesta de otro
            $table->foreignId('parent_comment_id')->nullable()->constrained('comments')->cascadeOnDelete();
            $table->foreignId('report_id')->constrained('reports')->cascadeOnDelete();
            $table->foreignUuid('creator_id')->constrained('users')->restrictOnDelete();

            $table->index(['report_id', 'parent_comment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
