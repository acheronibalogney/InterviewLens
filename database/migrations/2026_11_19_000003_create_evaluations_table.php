<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->restrictOnDelete();
            $table->text('answer');
            $table->unsignedTinyInteger('score');
            $table->unsignedSmallInteger('word_count');
            $table->unsignedSmallInteger('filler_count');
            $table->json('star_components');
            $table->json('matched_keywords');
            $table->json('feedback');
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluations');
    }
};
