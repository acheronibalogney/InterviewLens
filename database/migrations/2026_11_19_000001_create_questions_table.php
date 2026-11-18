<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table): void {
            $table->id();
            $table->string('category', 80);
            $table->string('question', 500);
            $table->json('keywords');
            $table->boolean('is_behavioral')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['category', 'question']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
