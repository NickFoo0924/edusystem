<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Progress is tracked per student per course, never as one global percentage
 * (EduSystem.md 1B). The composite unique key is what guarantees that.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('student_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->integer('materials_viewed')->default(0);
            $table->integer('quizzes_passed')->default(0);
            $table->integer('assignments_submitted')->default(0);
            $table->double('completion_percentage')->default(0);
            $table->dateTime('last_calculated_at')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'course_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_progress');
    }
};
