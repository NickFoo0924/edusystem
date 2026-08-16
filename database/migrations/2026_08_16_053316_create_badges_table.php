<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A badge is a rule an Administrator configures, never a hardcoded award
 * (EduSystem.md 1D). criteria_type names what to measure and criteria_value the
 * threshold, e.g. quiz_score >= 90.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('badges', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description');
            // Nullable: a badge without a custom icon falls back to the built-in
            // medal for its tier, so an administrator can define a rule now and
            // upload artwork later.
            $table->string('icon_path')->nullable();
            $table->enum('tier', ['bronze', 'silver', 'gold']);
            $table->enum('criteria_type', [
                'quiz_score',
                'course_completion',
                'path_completion',
                'on_time_submissions',
                'first_forum_post',
                'login_streak',
            ]);
            $table->integer('criteria_value');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('badges');
    }
};
