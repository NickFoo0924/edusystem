<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Reply;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * MODULE 3 -- answering a question. Also an observed subject, so the person who
 * asked hears about it.
 */
class ReplyController extends Controller
{
    public function store(Request $request, Post $post): RedirectResponse
    {
        $forum = $post->forum;
        $course = $forum->course;
        $user = $request->user();

        abort_unless($user->can('forum.post'), 403, 'Administrators do not take part in forums.');
        abort_unless(
            $course->instructor_id === $user->id || $course->hasStudent($user),
            403,
            'You are not enrolled in this course.'
        );

        $data = $request->validate([
            'content' => ['required', 'string', 'min:2', 'max:5000'],
        ]);

        Reply::create([
            'post_id' => $post->id,
            'user_id' => $user->id,
            'content' => $data['content'],
        ]);

        return redirect()->route('forums.show', $forum)->with('success', 'Reply posted.');
    }

    public function destroy(Request $request, Reply $reply): RedirectResponse
    {
        $forum = $reply->post->forum;

        abort_unless(
            $reply->user_id === $request->user()->id
                || ($request->user()->can('forum.moderate') && $forum->course->instructor_id === $request->user()->id),
            403
        );

        $reply->delete();

        return redirect()->route('forums.show', $forum)->with('success', 'Reply removed.');
    }
}
