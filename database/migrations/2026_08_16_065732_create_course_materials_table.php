<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 2 (Foo Chong Xian). A material is either an uploaded file or a link to
 * an external resource; is_external is the flag the Adapter pattern switches on
 * so both render through one interface (EduSystem.md Section 2).
 *
 * `title` is an addition to the Section 3 column list -- a resource list is
 * unusable when every row is only a file path.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->enum('type', ['lecture', 'tutorial', 'practical']);
            $table->string('file_path');
            $table->boolean('is_external')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_materials');
    }
};
