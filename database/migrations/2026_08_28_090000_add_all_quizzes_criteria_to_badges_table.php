<?php

/**
 * LearnSync -- Database migration
 *
 * Module 1: Identity, Access & Digital Credentialing
 *
 * @author Serena Lim Sze Kee
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A seventh badge criterion: clearing every quiz in one subject.
 *
 * Two changes, and the second is the interesting one.
 *
 * `criteria_type` gains `all_quizzes_in_course`. Every other criterion in the
 * registry measures something global to a student -- how many courses they have
 * finished, their best quiz score, their login streak -- so a single row could
 * express the whole rule. This one is per-subject: "every quiz in Integrative
 * Programming" is a different achievement from "every quiz in Enterprise
 * Networking", and a student should be able to hold both.
 *
 * The composite unique key on badge_student means one badge row can only ever
 * be awarded to a student once, so per-subject awards require one badge row per
 * subject. `course_id` is what scopes a row to its subject.
 *
 * Nullable, and null is meaningful rather than merely absent:
 *   - set   -- "clear every quiz in THIS course", awarded once, e.g.
 *              "Subject Expert — Integrative Programming"
 *   - null  -- "clear every quiz in any `criteria_value` courses", which keeps
 *              criteria_value doing the same job it does for every other rule
 *
 * On delete the column nulls rather than cascading: cascading would remove the
 * badge and, with it, every award already in a student's cabinet. Badges
 * already earned are kept (see EduSystem.md 1D -- an award is a record of
 * something that happened).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('badges', function (Blueprint $table) {
            $table->enum('criteria_type', [
                'quiz_score',
                'course_completion',
                'path_completion',
                'on_time_submissions',
                'first_forum_post',
                'login_streak',
                'all_quizzes_in_course',
            ])->change();

            $table->foreignId('course_id')
                ->nullable()
                ->after('criteria_value')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('badges', function (Blueprint $table) {
            $table->dropConstrainedForeignId('course_id');

            $table->enum('criteria_type', [
                'quiz_score',
                'course_completion',
                'path_completion',
                'on_time_submissions',
                'first_forum_post',
                'login_streak',
            ])->change();
        });
    }
};
