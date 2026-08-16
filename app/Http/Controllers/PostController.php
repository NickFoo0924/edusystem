<?php

namespace App\Http\Controllers;

use App\Models\DiscussionForum;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * MODULE 3 -- asking a question.
 *
 * Creating the Post is all this controller does. The notification that reaches
 * the instructor is written by SystemNotificationObserver, which Eloquent
 * invokes on the created event. That decoupling is the Observer pattern.
 */
class PostController extends Controller
{
    public function store(Request $request, DiscussionForum $forum): RedirectResponse
    {
        $this->authoriseParticipant($request, $forum);

        $data = $request->validate([
            'content' => ['required', 'string', 'min:3', 'max:5000'],
        ]);

        Post::create([
            'forum_id' => $forum->id,
            'user_id' => $request->user()->id,
            'content' => $data['content'],
        ]);

        return redirect()->route('forums.show', $forum)->with('success', 'Posted.');
    }

    public function destroy(Request $request, Post $post): RedirectResponse
    {
        $forum = $post->forum;

        // Authors delete their own; instructors moderate their course's forum.
        abort_unless(
            $post->user_id === $request->user()->id
                || ($request->user()->can('forum.moderate') && $forum->course->instructor_id === $request->user()->id),
            403
        );

        $post->delete();

        return redirect()->route('forums.show', $forum)->with('success', 'Post removed.');
    }

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
