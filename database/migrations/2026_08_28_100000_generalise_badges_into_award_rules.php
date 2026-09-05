<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Turns the badge registry into a general AWARD RULE registry.
 *
 * The investigation behind this (see docs/award-rules.md): badge rules were
 * already data an administrator edits, but certificate issuance was not. The
 * only way a certificate could ever be minted was one hardcoded condition in
 * CredentialAuthority::issueIfEligible() -- completion percentage past a
 * threshold, plus a passing average. An administrator could tune that one
 * number in `settings` and design the template it renders, but could not say
 * "issue a certificate for averaging 75% in this subject". That is what these
 * columns add.
 *
 * The table keeps the name `badges` rather than being renamed to `award_rules`.
 * Renaming would have to carry badge_student.badge_id and every existing award
 * with it, and this project's convention for schema changes is to add columns
 * and document them (see implementation-notes.md, "Added columns") rather than
 * to restructure tables that already hold assessed data.
 *
 *   award_type              -- 'badge' (attach to badge_student, the existing
 *                              behaviour and the default, so every row already
 *                              in the table keeps working untouched) or
 *                              'certificate' (mint through the
 *                              CredentialAuthority)
 *   certificate_template_id -- which design a certificate rule renders; null
 *                              for badge rules, which use tier + icon instead
 *
 * criteria_type also gains the two parameterised conditions the task named that
 * the registry could not yet express. "Attend N classes" is deliberately absent:
 * nothing in this system records attendance, so a rule referring to it could
 * never become true and would sit permanently unearnable in every cabinet.
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
                'average_score_in_course',
                'quizzes_completed',
            ])->change();

            $table->enum('award_type', ['badge', 'certificate'])
                ->default('badge')
                ->after('description');

            $table->foreignId('certificate_template_id')
                ->nullable()
                ->after('course_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('badges', function (Blueprint $table) {
            $table->dropConstrainedForeignId('certificate_template_id');
            $table->dropColumn('award_type');

            $table->enum('criteria_type', [
                'quiz_score',
                'course_completion',
                'path_completion',
                'on_time_submissions',
                'first_forum_post',
                'login_streak',
                'all_quizzes_in_course',
            ])->change();
        });
    }
};
