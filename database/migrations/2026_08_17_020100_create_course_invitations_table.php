<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 2. An instructor asking one named student into one course.
 *
 * Separate from `invitations`, which is Module 1's account-creation flow: that
 * one brings a person into the system at all, this one brings an existing
 * account into a course. A row here is not an enrolment -- the student still
 * has to accept, which is what puts them in `course_student`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            // One standing invitation per student per course. Re-inviting
            // someone already invited is a no-op, not a second row.
            $table->unique(['course_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_invitations');
    }
};
