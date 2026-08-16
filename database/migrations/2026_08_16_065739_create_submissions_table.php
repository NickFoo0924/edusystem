<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 5. `state` is the State pattern's discriminator: draft, submitted or
 * graded. The state object decides whether updateFile() and assignGrade() are
 * allowed (EduSystem.md Section 2).
 *
 * Two additions to the Section 3 column list:
 *   submitted_at -- needed to tell an on-time submission from a late one, which
 *                   the on_time_submissions badge rule depends on
 *   the composite unique key -- one submission per student per assignment
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('file_path')->nullable();
            $table->string('state')->default('draft');
            $table->dateTime('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['assignment_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};
