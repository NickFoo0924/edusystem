<?php

/**
 * LearnSync -- Database migration
 *
 * Module 1: Identity, Access & Digital Credentialing
 *
 * @author Serena Lim Sze Kee
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 3. What a notification is *about*, so it can be sent only once.
 *
 * Event-driven notifications never needed this: a forum reply happens once, so
 * writing a row when it happens cannot duplicate. Reminders are different --
 * the scheduler asks "what is due soon?" every few minutes, and would answer
 * the same thing every time. Deduplicating on the link is not enough, because
 * lecturers reuse one meeting URL across a whole term's classes, which would
 * silently suppress every reminder after the first.
 *
 * So each reminder carries a stable identity for its subject, e.g. "event:12"
 * or "assignment_due:3", and the sender refuses to write a second row for the
 * same recipient, type and reference.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('reference')->nullable()->after('link');

            // Every send does one "have I already told them this?" lookup.
            $table->index(['user_id', 'type', 'reference']);
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'type', 'reference']);
            $table->dropColumn('reference');
        });
    }
};
