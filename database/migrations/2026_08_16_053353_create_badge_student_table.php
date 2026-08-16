<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which student has earned which badge. The composite unique key is what stops
 * the rules engine awarding the same badge twice (EduSystem.md Section 3 #13).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('badge_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('badge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('awarded_at');

            $table->unique(['badge_id', 'student_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('badge_student');
    }
};
