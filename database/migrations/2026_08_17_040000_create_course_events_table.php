<?php

/**
 * LearnSync -- Database migration
 *
 * Module 2: Academic Resources Repository
 *
 * @author Foo Chong Xian
 */

use App\Models\Permission;
use App\Models\PermissionRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 2 -- a scheduled class, online meeting or briefing.
 *
 * Only the things a person puts in a diary live here. Assignment deadlines
 * deliberately do NOT get a row: they already exist as `assignments.due_date`,
 * and copying them would create two dates that can disagree the moment an
 * instructor moves one. The calendar reads both sources and adapts them to a
 * common interface instead, which is why this table can stay this small.
 *
 * A null course_id is an institution-wide event from an administrator, exactly
 * as it means on `announcements`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            // 'class' and 'meeting' read differently on a timetable, and the
            // calendar colours them apart.
            $table->enum('type', ['class', 'meeting', 'other'])->default('class');
            // Either or both: a room for people in the building, a link for
            // those who are not.
            $table->string('location')->nullable();
            $table->string('meeting_url')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->timestamps();

            // Every query is "what is on between these two dates".
            $table->index(['starts_at']);
        });

        /*
         * Inserted here as well as in the seeder, so an existing database picks
         * the key up from a plain `migrate` while a fresh `migrate:fresh --seed`
         * rebuilds the whole matrix from the seeder. The seeder uses
         * updateOrCreate, so the two paths cannot collide.
         */
        $permission = Permission::firstOrCreate(
            ['key' => 'event.manage'],
            ['label' => 'Schedule classes and meetings', 'group' => 'Course Management']
        );

        foreach (['admin', 'instructor'] as $role) {
            PermissionRole::firstOrCreate(['permission_id' => $permission->id, 'role' => $role]);
        }
    }

    public function down(): void
    {
        $permission = Permission::where('key', 'event.manage')->first();

        if ($permission) {
            PermissionRole::where('permission_id', $permission->id)->delete();
            $permission->delete();
        }

        Schema::dropIfExists('course_events');
    }
};
