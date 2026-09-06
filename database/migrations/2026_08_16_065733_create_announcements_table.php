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
 * Module 2. A null course_id means a global announcement from an administrator;
 * a set one means an instructor addressing their own course. Renamed from
 * admin_id to author_id in the v2 revision (EduSystem.md Section 3 #22).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->text('content');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
