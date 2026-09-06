<?php

/**
 * LearnSync -- Database migration
 *
 * Module 4: Skill Assessment & Quiz
 *
 * @author Wong Siew Lam
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 4. `type` is what the Strategy pattern switches on: "mcq" selects
 * MCQGradingStrategy, "text" selects TextMatchGradingStrategy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->text('question_text');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
