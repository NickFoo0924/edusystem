<?php

namespace App\Http\Controllers;

use App\Models\NotificationPreference;
use App\Patterns\Observer\SystemNotificationObserver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * MODULE 1 (1E) -- per-user control over which notification types arrive.
 *
 * The Observer consults these before writing a row, so switching a type off
 * genuinely stops it being produced rather than merely hiding it.
 */
class NotificationPreferenceController extends Controller
{
    /**
     * The switchable types, with the wording shown to the user.
     */
    public const TYPES = [
        SystemNotificationObserver::TYPE_NEW_POST => 'Someone posts a question in a course I teach',
        SystemNotificationObserver::TYPE_NEW_REPLY => 'Someone replies to my forum post',
        SystemNotificationObserver::TYPE_ANNOUNCEMENT_COMMENT => 'Someone comments on my announcement',
        'certificate.issued' => 'I earn a new certificate',
        'badge.awarded' => 'I earn a new badge',
    ];

    public function edit(Request $request): View
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

    public function update(Request $request): RedirectResponse
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
