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
 * Per-assignment late submission policy (Module 5).
 *
 * true  -- work handed in after the deadline is still accepted, and marked
 *          "Turned in late" wherever it appears
 * false -- the assignment closes at its deadline and nothing further is taken
 *
 * Defaults to true so an instructor who never touches the setting gets the
 * forgiving behaviour, and existing assignments keep working unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->boolean('allow_late_submission')->default(true)->after('due_date');
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn('allow_late_submission');
        });
    }
};
