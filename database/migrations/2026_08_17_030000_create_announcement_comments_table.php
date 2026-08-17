<?php

use App\Models\Permission;
use App\Models\PermissionRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 2/3 -- the discussion under an announcement.
 *
 * An announcement used to be a one-way broadcast, which meant the obvious
 * question ("is this assessed?") had nowhere to go but a separate forum thread
 * detached from the notice that prompted it. This keeps the exchange attached
 * to what it is about.
 *
 * Flat on purpose: no parent_id, no nesting. It is a short conversation under
 * a notice, and threading it would invite the forum's job to be done here
 * badly. Anything that needs a real thread belongs in Module 3's forum.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcement_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();

            // The thread is always read oldest-first for one announcement.
            $table->index(['announcement_id', 'created_at']);
        });

        /*
         * The permission is inserted here as well as in the seeder, because the
         * two arrive by different routes: a fresh `migrate:fresh --seed`
         * rebuilds the matrix from the seeder, while an existing database only
         * ever runs migrations. Without this, upgrading in place would leave a
         * comment box that nobody holds the permission to use.
         */
        $permission = Permission::firstOrCreate(
            ['key' => 'announcement.comment'],
            ['label' => 'Comment on an announcement', 'group' => 'Course Management']
        );

        foreach (['instructor', 'student'] as $role) {
            PermissionRole::firstOrCreate(['permission_id' => $permission->id, 'role' => $role]);
        }
    }

    public function down(): void
    {
        $permission = Permission::where('key', 'announcement.comment')->first();

        if ($permission) {
            PermissionRole::where('permission_id', $permission->id)->delete();
            $permission->delete();
        }

        Schema::dropIfExists('announcement_comments');
    }
};
