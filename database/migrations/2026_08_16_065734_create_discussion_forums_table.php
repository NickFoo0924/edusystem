<?php

/**
 * LearnSync -- Database migration
 *
 * Module 3: Student Forum & Notifications
 *
 * @author Ong Shun Yan
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 3 (Ong Shun Yan). One forum per course -- the unique key on course_id
 * is what makes the relationship one-to-one (EduSystem.md Section 3 #23).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discussion_forums', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discussion_forums');
    }
};
