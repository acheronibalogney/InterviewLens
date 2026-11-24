<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->renameColumn('name', 'full_name');
            $table->string('role', 30)->default('job_seeker')->index();
        });
        Schema::table('questions', function (Blueprint $table): void {
            $table->renameColumn('question', 'question_text');
            $table->renameColumn('keywords', 'key_terms');
        });
        Schema::rename('evaluations', 'attempts');
        Schema::table('attempts', function (Blueprint $table): void {
            $table->renameColumn('answer', 'answer_text');
            $table->renameColumn('score', 'total_score');
        });
        Schema::create('feedback', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attempt_id')->constrained('attempts')->cascadeOnDelete();
            $table->string('criterion', 40);
            $table->unsignedTinyInteger('criterion_score');
            $table->text('tip');
            $table->timestamps();
            $table->unique(['attempt_id', 'criterion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback');
        Schema::table('attempts', function (Blueprint $table): void {
            $table->renameColumn('answer_text', 'answer');
            $table->renameColumn('total_score', 'score');
        });
        Schema::rename('attempts', 'evaluations');
        Schema::table('questions', function (Blueprint $table): void {
            $table->renameColumn('question_text', 'question');
            $table->renameColumn('key_terms', 'keywords');
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['role']);
            $table->dropColumn('role');
            $table->renameColumn('full_name', 'name');
        });
    }
};
