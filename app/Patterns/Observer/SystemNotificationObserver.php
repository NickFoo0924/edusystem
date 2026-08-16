<?php

namespace App\Patterns\Observer;

use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\Post;
use App\Models\Reply;
use Illuminate\Database\Eloquent\Model;

/**
 * MODULE 3 DESIGN PATTERN -- OBSERVER (Behavioural).
 *
 * The Post (and Reply) is the SUBJECT; this class is the OBSERVER. Eloquent's
 * model-observer mechanism is the notify() call: saving a Post broadcasts a
 * `created` event, and every registered observer is invoked automatically.
 *
 * The point of the pattern here is that Post knows nothing about
 * notifications. Module 3 produces the events; Module 1 owns the inbox that
 * displays them (EduSystem.md Section 2A). Neither module has to import the
 * other, and a new observer could be added later -- an email digest, say --
 * without touching the forum code at all.
 *
 * Registered in AppServiceProvider via Post::observe() and Reply::observe().
 */
class SystemNotificationObserver
{
    /**
     * Notification type keys. Users can switch each off individually through
     * their notification preferences.
     */
    public const TYPE_NEW_POST = 'forum.post';

    public const TYPE_NEW_REPLY = 'forum.reply';

    /**
     * Fired by Eloquent when a Post or a Reply is created.
     *
     * One observer serves both subjects because the responsibility is the
     * same -- turn forum activity into inbox entries.
     */
    public function created(Model $model): void
    {
        match (true) {
            $model instanceof Post => $this->onPostCreated($model),
            $model instanceof Reply => $this->onReplyCreated($model),
            default => null,
        };
    }

    /**
     * A student asked something: tell the course instructor.
     */
    private function onPostCreated(Post $post): void
    {
        $post->loadMissing(['forum.course', 'author']);

        $course = $post->forum?->course;

        if ($course === null || $course->instructor_id === $post->user_id) {
            return;
        }

        $this->notify(
            userId: $course->instructor_id,
            type: self::TYPE_NEW_POST,
            message: "{$post->author->name} posted in {$course->title}",
            link: route('forums.show', $post->forum_id).'#post-'.$post->id,
        );
    }

    /**
     * Somebody answered a post: tell whoever wrote it.
     */
    private function onReplyCreated(Reply $reply): void
    {
        $reply->loadMissing(['post.forum', 'author']);

        $post = $reply->post;

        // Replying to yourself is not news.
        if ($post === null || $post->user_id === $reply->user_id) {
            return;
        }

        $this->notify(
            userId: $post->user_id,
            type: self::TYPE_NEW_REPLY,
            message: "{$reply->author->name} replied to your question",
            link: route('forums.show', $post->forum_id).'#post-'.$post->id,
        );
    }

    /**
     * Write the inbox row, unless the recipient has switched this type off.
     *
     * Preferences are opt-out: a missing row means the user has never changed
     * the setting, and silence should not mean "send nothing".
     */
    private function notify(int $userId, string $type, string $message, string $link): void
    {
        $preference = NotificationPreference::where('user_id', $userId)
            ->where('type', $type)
            ->first();

        if ($preference !== null && ! $preference->enabled) {
            return;
        }

        Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'message' => $message,
            'link' => $link,
            'is_read' => false,
        ]);
    }
}
