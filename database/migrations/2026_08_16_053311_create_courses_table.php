<?php

/**
 * LearnSync -- Database migration
 *
 * Module 2: Academic Resources Repository
 *
 * @author Foo Chong Xian
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 2 owns this table (Foo Chong Xian). Module 1 only ever reads from it,
 * so that certificates can be named after a course and learning paths can be
 * assembled. See EduSystem.md Section 2A.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instructor_id')->constrained('users')->cascadeOnDelete();
            // Addition to the Section 3 column list: real courses are known by
            // their code as much as their name, e.g. BMIT3173. Unique because a
            // code identifies exactly one course.
            $table->string('code')->unique();
            $table->string('title');
            $table->text('description');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
