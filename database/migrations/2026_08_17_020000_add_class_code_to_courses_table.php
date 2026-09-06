<?php

/**
 * LearnSync -- Database migration
 *
 * Module 2: Academic Resources Repository
 *
 * @author Foo Chong Xian
 */

use App\Models\Course;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 2. The join code a student types to enrol themselves.
 *
 * Deliberately not the same thing as `courses.code`. That one (BMIT3173) is
 * public, guessable and printed on every timetable -- if it let you into a
 * course, the enrolment policy would be no policy at all. This one is a random
 * eight characters the instructor hands out, so possessing it is itself the
 * evidence that the instructor meant you to join.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Added nullable so existing rows survive the column appearing, then
        // filled and only afterwards made unique -- a unique index cannot be
        // laid over a column that is briefly all-NULL on a populated table.
        Schema::table('courses', function (Blueprint $table) {
            $table->string('class_code', 8)->nullable()->after('code');
        });

        Course::whereNull('class_code')->get()->each(function (Course $course) {
            $course->forceFill(['class_code' => Course::generateClassCode()])->save();
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->string('class_code', 8)->nullable(false)->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropUnique(['class_code']);
            $table->dropColumn('class_code');
        });
    }
};
