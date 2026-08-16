<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contact details for the public lecturer profile.
 *
 * A student needs a reliable way to reach the person teaching them, so the
 * email address is always shown. A phone number is different: publishing one
 * invites messages at any hour, so it is both optional to provide and hidden
 * by default even when provided.
 *
 * Two columns rather than one, because "I have not given a number" and "I have
 * given one but do not want students to have it" are different states. A
 * lecturer can record a number for the department without exposing it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->after('bio');
            $table->boolean('show_phone')->default(false)->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'show_phone']);
        });
    }
};
