<?php

/**
 * LearnSync -- HTTP controller
 *
 * Module 3: Student Forum & Notifications
 *
 * @author Ong Shun Yan
 */

namespace App\Http\Controllers;

use App\Models\DiscussionForum;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use App\Models\Reply;

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

    /*
     * Posts. The Observer is attached to the Post model rather than to this
     * controller, so writing one here still raises the notification without
     * this method knowing that notifications exist (EduSystem.md Section 2).
     */
    public function storePost(Request $request, DiscussionForum $forum): RedirectResponse
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

    public function destroyPost(Request $request, Post $post): RedirectResponse
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

    

    /*
     * Replies, on the same terms as posts above.
     */
    public function storeReply(Request $request, Post $post): RedirectResponse
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

    public function destroyReply(Request $request, Reply $reply): RedirectResponse
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
