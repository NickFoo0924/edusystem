<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Console\Commands\SendScheduledReminders;
use App\Models\NotificationPreference;
use App\Patterns\Facade\Subsystem\BadgeRuleEvaluator;
use App\Patterns\Observer\SystemNotificationObserver;

/**
 * MODULE 1 (1E) -- the notification inbox.
 *
 * Module 3's Observer produces the rows; Module 1 owns everything the user sees
 * of them: the bell, the unread count, the history page and mark-as-read
 * (EduSystem.md Section 2A).
 */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $notifications = Notification::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(30);

        return view('notifications.index', compact('notifications'));
    }

    /**
     * Mark one as read and follow it to wherever it points.
     */
    public function read(Request $request, Notification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        $notification->update(['is_read' => true]);

        return redirect($notification->link ?? route('notifications.index'));
    }

    public function readAll(Request $request): RedirectResponse
    {
        Notification::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return back()->with('success', 'All notifications marked as read.');
    }

    public function destroy(Request $request, Notification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        $notification->delete();

        return back()->with('success', 'Notification removed.');
    }

    /*
     * Per-user notification preferences (EduSystem.md 1E).
     *
     * Two methods rather than a resource: there is one preferences record per
     * user and it is only ever edited in place. The sender consults these
     * before writing a row, so switching a type off stops it being produced
     * rather than merely hiding it.
     */
    /**
     * The switchable types, with the wording shown to the user.
     */
    public const TYPES = [
        SystemNotificationObserver::TYPE_NEW_POST => 'Someone posts a question in a course I teach',
        SystemNotificationObserver::TYPE_NEW_REPLY => 'Someone replies to my forum post',
        SystemNotificationObserver::TYPE_MENTION => 'Someone mentions me with an @ in a forum message',
        SystemNotificationObserver::TYPE_ANNOUNCEMENT_COMMENT => 'Someone comments on my announcement',
        SendScheduledReminders::TYPE_EVENT_SOON => 'A class or meeting on my calendar is about to start',
        SendScheduledReminders::TYPE_ASSIGNMENT_DUE => 'An assignment I have not submitted is due soon',
        SendScheduledReminders::TYPE_ASSIGNMENT_CLOSED => 'An assignment I set has closed and has work to review',
        SystemNotificationObserver::TYPE_ANNOUNCEMENT_POSTED => 'An announcement is posted to a course I am in',
        SystemNotificationObserver::TYPE_GRADE_RECORDED => 'My submitted work has been marked',
        SystemNotificationObserver::TYPE_COURSE_INVITATION => 'A lecturer invites me to join a course',
        /*
         * These two were listed here long before anything produced them --
         * the switches existed for notifications that never arrived. Both now
         * have a producer, and the string literals have been replaced with the
         * constants their senders declare, so the two cannot drift apart.
         */
        SystemNotificationObserver::TYPE_CERTIFICATE_ISSUED => 'I earn a new certificate',
        BadgeRuleEvaluator::TYPE_BADGE_AWARDED => 'I earn a new badge',
    ];

    public function editPreferences(Request $request): View
    {
        abort_unless($request->user()->can('notification.preferences'), 403);

        // Missing rows mean "never changed", which is treated as enabled.
        $current = NotificationPreference::where('user_id', $request->user()->id)
            ->pluck('enabled', 'type');

        return view('notifications.preferences', [
            'types' => self::TYPES,
            'current' => $current,
        ]);
    }

    public function updatePreferences(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('notification.preferences'), 403);

        $enabled = $request->input('types', []);

        foreach (array_keys(self::TYPES) as $type) {
            NotificationPreference::updateOrCreate(
                ['user_id' => $request->user()->id, 'type' => $type],
                ['enabled' => in_array($type, $enabled, true)]
            );
        }

        return redirect()->route('notifications.preferences.edit')
            ->with('success', 'Notification preferences saved.');
    }
}
