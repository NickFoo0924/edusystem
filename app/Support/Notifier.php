<?php

/**
 * LearnSync -- Support helper
 *
 * Module 3: Student Forum & Notifications
 *
 * @author Ong Shun Yan
 */

namespace App\Support;

use App\Models\Notification;
use App\Models\NotificationPreference;

/**
 * MODULE 3 -- the one place an inbox row gets written.
 *
 * Two very different producers feed it. The Observer writes when something
 * happens (a reply is posted); the reminder command writes when a time
 * approaches (a class starts in an hour). Both must honour the recipient's
 * notification preferences, and preferences are the kind of rule that rots the
 * moment it is implemented twice, so it lives here.
 */
class Notifier
{
    /**
     * Write an inbox row unless the recipient has switched this type off.
     *
     * Preferences are opt-out: a missing row means the user has never changed
     * the setting, and silence should not mean "send nothing".
     *
     * `$reference` identifies what the notification is about, e.g. "event:12".
     * Given one, the same recipient is never told the same thing twice --
     * which is what makes a reminder safe to run on a schedule.
     *
     * @return bool  Whether a row was actually written.
     */
    public static function send(
        int $userId,
        string $type,
        string $message,
        string $link,
        ?string $reference = null
    ): bool {
        $preference = NotificationPreference::where('user_id', $userId)
            ->where('type', $type)
            ->first();

        if ($preference !== null && ! $preference->enabled) {
            return false;
        }

        if ($reference !== null && self::alreadySent($userId, $type, $reference)) {
            return false;
        }

        Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'message' => $message,
            'link' => $link,
            'reference' => $reference,
            'is_read' => false,
        ]);

        return true;
    }

    /**
     * Has this person already been told this particular thing?
     */
    public static function alreadySent(int $userId, string $type, string $reference): bool
    {
        return Notification::where('user_id', $userId)
            ->where('type', $type)
            ->where('reference', $reference)
            ->exists();
    }
}
