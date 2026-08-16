<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The institutional address shown on a lecturer's public contact card.
 *
 * Kept separate from `email`, which is the login identity and may well be a
 * personal address. A student contacting a lecturer should be given the
 * official one, and a lecturer should not have to expose a personal inbox to
 * appear in the directory.
 *
 * Nullable, with the account email as the fallback, so a contact card can never
 * end up with no address at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('school_email')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('school_email');
        });
    }
};
