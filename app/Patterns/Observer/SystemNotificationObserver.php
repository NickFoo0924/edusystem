<?php

namespace App\Patterns\Observer;

use App\Models\AnnouncementComment;
use App\Models\Course;
use App\Models\Post;
use App\Models\Reply;
use App\Models\User;
use App\Support\Mentions;
use App\Support\Notifier;
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

    public const TYPE_ANNOUNCEMENT_COMMENT = 'announcement.comment';

    public const TYPE_MENTION = 'forum.mention';

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
            $model instanceof AnnouncementComment => $this->onAnnouncementCommentCreated($model),
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

        if ($course === null) {
            return;
        }

        $link = route('forums.show', $post->forum_id).'#post-'.$post->id;

        // Anyone named with an @ hears about it first, and hearing about it
        // once is enough -- so the instructor notice below is skipped for
        // someone who has already been told they were mentioned.
        $mentioned = $this->notifyMentions($post->content, $course, $post->author, $link);

        if ($course->instructor_id === $post->user_id || $mentioned->contains($course->instructor_id)) {
            return;
        }

        $this->notify(
            userId: $course->instructor_id,
            type: self::TYPE_NEW_POST,
            message: "{$post->author->name} posted in {$course->title}",
            link: $link,
        );
    }

    /**
     * Somebody answered a post: tell whoever wrote it.
     */
    private function onReplyCreated(Reply $reply): void
    {
        $reply->loadMissing(['post.forum', 'author']);

        $post = $reply->post;
        $course = $post?->forum?->course;

        if ($post === null || $course === null) {
            return;
        }

        $link = route('forums.show', $post->forum_id).'#post-'.$post->id;

        $mentioned = $this->notifyMentions($reply->content, $course, $reply->author, $link);

        // Replying to yourself is not news, and neither is being told twice.
        if ($post->user_id === $reply->user_id || $mentioned->contains($post->user_id)) {
            return;
        }

        $this->notify(
            userId: $post->user_id,
            type: self::TYPE_NEW_REPLY,
            message: "{$reply->author->name} replied to your question",
            link: $link,
        );
    }

    /**
     * Tell everyone named with an @ in a forum message.
     *
     * Candidates are drawn from the course, so a mention cannot reach somebody
     * outside the conversation, and naming yourself notifies nobody.
     *
     * @return \Illuminate\Support\Collection<int, int>  ids actually notified
     */
    private function notifyMentions(string $body, Course $course, ?User $author, string $link)
    {
        return Mentions::parse($body, $course)
            ->reject(fn (User $user) => $author && $user->id === $author->id)
            ->each(fn (User $user) => $this->notify(
                userId: $user->id,
                type: self::TYPE_MENTION,
                message: ($author?->name ?? 'Someone')." mentioned you in {$course->title}",
                link: $link,
            ))
            ->pluck('id');
    }

    /**
     * Somebody commented under an announcement: tell whoever posted it.
     *
     * A third subject for the same observer, added without the announcement
     * code learning that notifications exist -- which is the whole claim the
     * pattern makes.
     */
    private function onAnnouncementCommentCreated(AnnouncementComment $comment): void
    {
        $comment->loadMissing(['announcement', 'author']);

        $announcement = $comment->announcement;

        // Commenting on your own notice is not news.
        if ($announcement === null || $announcement->author_id === $comment->user_id) {
            return;
        }

        $this->notify(
            userId: $announcement->author_id,
            type: self::TYPE_ANNOUNCEMENT_COMMENT,
            message: "{$comment->author->name} commented on your announcement",
            link: route('announcements.index').'#announcement-'.$announcement->id,
        );
    }

    /**
     * Hand the row to Module 3's shared sender, which applies the recipient's
     * notification preferences.
     *
     * No reference is passed: an observed event happens once, so there is
     * nothing to deduplicate. Reminders repeat on a schedule and do supply one
     * -- see the Notifier.
     */
    private function notify(int $userId, string $type, string $message, string $link): void
    {
        Notifier::send($userId, $type, $message, $link);
    }
}
