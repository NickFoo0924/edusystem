<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\AnnouncementComment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * MODULE 2 -- the conversation under an announcement.
 *
 * Instructors and students both take part, which is what the
 * announcement.comment permission expresses. Administrators are excluded for
 * the same reason Section 7 keeps them out of forums: they run the system,
 * they are not participants in a class discussion.
 */
class AnnouncementCommentController extends Controller
{
    public function store(Request $request, Announcement $announcement): RedirectResponse
    {
        abort_unless($request->user()->can('announcement.comment'), 403);

        /*
         * Visibility is checked separately from the permission. Holding
         * announcement.comment says you may join a conversation; it does not
         * say which -- without this, a student could post under a course
         * announcement they are not enrolled in by guessing its id.
         */
        abort_unless($announcement->isVisibleTo($request->user()), 403,
            'This announcement is not addressed to you.');

        $data = $request->validate([
            'body' => ['required', 'string', 'max:1000'],
        ], [], ['body' => 'comment']);

        AnnouncementComment::create([
            'announcement_id' => $announcement->id,
            'user_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        // Back to the comment that was just written, with the thread open.
        return back()->withFragment('announcement-'.$announcement->id);
    }

    public function destroy(Request $request, Announcement $announcement, AnnouncementComment $comment): RedirectResponse
    {
        abort_unless($comment->announcement_id === $announcement->id, 404);

        /*
         * Your own comment, or the announcement author moderating their own
         * notice. An administrator can reach any of it, which is consistent
         * with them being able to delete the announcement itself.
         */
        $allowed = $comment->user_id === $request->user()->id
            || $announcement->author_id === $request->user()->id
            || $request->user()->can('analytics.view_system');

        abort_unless($allowed, 403);

        $comment->delete();

        return back()->withFragment('announcement-'.$announcement->id);
    }
}
