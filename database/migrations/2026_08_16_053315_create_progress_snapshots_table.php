<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per recalculation, so the student dashboard can draw a
 * progress-over-time line chart with Chart.js (EduSystem.md 1B).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('progress_snapshots', function (Blueprint $table) {
            $table->id();
            // Named explicitly: Laravel would otherwise guess `student_progresses`.
            $table->foreignId('student_progress_id')->constrained('student_progress')->cascadeOnDelete();
            $table->double('completion_percentage');
            $table->dateTime('captured_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('progress_snapshots');
    }
};
