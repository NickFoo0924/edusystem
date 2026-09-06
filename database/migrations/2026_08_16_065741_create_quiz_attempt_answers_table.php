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
 * NOT IN EduSystem.md SECTION 3 -- a necessary addition.
 *
 * Section 3 gives quiz_attempts only a duration, with nowhere to keep what the
 * student actually answered. Without this table a quiz cannot be graded and an
 * attempt cannot be reviewed afterwards. One row per question per attempt,
 * holding the response and what the grading Strategy made of it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quiz_attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            // Free text for a fill-in-the-blank, or the chosen answer id as a
            // string for an MCQ -- one column serves both question types.
            $table->text('response')->nullable();
            $table->boolean('is_correct')->default(false);
            $table->double('awarded_score')->default(0);
            $table->timestamps();

            $table->unique(['quiz_attempt_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_attempt_answers');
    }
};
