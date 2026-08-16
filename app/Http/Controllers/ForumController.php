<?php

namespace App\Http\Controllers;

use App\Models\DiscussionForum;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * MODULE 3 (Ong Shun Yan) -- the course Q&A forum.
 *
 * Section 7 is explicit that administrators cannot participate in forums, which
 * is why access is gated on forum.post rather than on being logged in.
 */
class ForumController extends Controller
{
    public function show(Request $request, DiscussionForum $forum): View
    {
        $this->authoriseParticipant($request, $forum);

        $forum->load([
            'course',
            'posts.author',
            'posts.replies.author',
        ]);

        return view('forums.show', [
            'forum' => $forum,
            'posts' => $forum->posts->sortByDesc('created_at'),
        ]);
    }

    /**
     * Only the course instructor and enrolled students may read or write here.
     */
    private function authoriseParticipant(Request $request, DiscussionForum $forum): void
    {
        $user = $request->user();
        $course = $forum->course;

        abort_unless($user->can('forum.post'), 403, 'Administrators do not take part in forums.');
        abort_unless(
            $course->instructor_id === $user->id || $course->hasStudent($user),
            403,
            'You are not enrolled in this course.'
        );
    }
}
