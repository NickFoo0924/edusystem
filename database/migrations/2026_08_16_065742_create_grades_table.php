<?php

/**
 * LearnSync -- Database migration
 *
 * Module 5: Academic Progress Analytics
 *
 * @author Ong Kwong Wei
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 5 -- the authoritative mark, and the only table Module 5 writes that
 * Module 1 reads (EduSystem.md Section 2A).
 *
 * Exactly one of submission_id / quiz_attempt_id is set: a grade comes either
 * from marked coursework or from a quiz sitting. Both are unique, so one piece
 * of work can never carry two grades.
 *
 * Writing a row here is what triggers the CredentialAuthority in workflow
 * Step 5: recalculate progress, evaluate badges, then issue any certificate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('quiz_attempt_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->double('calculated_score');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grades');
    }
};
