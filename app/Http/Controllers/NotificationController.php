<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

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
}
