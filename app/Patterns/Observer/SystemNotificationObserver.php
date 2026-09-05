<?php

namespace App\Patterns\Observer;

use App\Models\Announcement;
use App\Models\AnnouncementComment;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseInvitation;
use App\Models\Grade;
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

    public const TYPE_ANNOUNCEMENT_POSTED = 'announcement.posted';

    public const TYPE_GRADE_RECORDED = 'grade.recorded';

    public const TYPE_CERTIFICATE_ISSUED = 'certificate.issued';

    public const TYPE_COURSE_INVITATION = 'course.invitation';

    /**
     * Fired by Eloquent when any observed model is created.
     *
     * SEVEN SUBJECTS, ONE OBSERVER, and none of them knows it exists. That is
     * the whole claim the pattern makes, and it is why the list below could
     * grow from three to seven without a single line changing in the forum, the
     * announcement screen, the grading flow or the credential authority: each
     * simply saves its model, and Eloquent does the notifying.
     *
     * Post, Reply and AnnouncementComment turn conversation into inbox
     * entries. Announcement, Grade, Certificate and CourseInvitation close the
     * gaps found when the module integrations were audited,
     * where the most significant events in the system -- earning a credential,
     * having work marked, being invited to a course -- happened silently.
     */
    public function created(Model $model): void
    {
        match (true) {
            $model instanceof Post => $this->onPostCreated($model),
            $model instanceof Reply => $this->onReplyCreated($model),
            $model instanceof AnnouncementComment => $this->onAnnouncementCommentCreated($model),
            $model instanceof Announcement => $this->onAnnouncementCreated($model),
            $model instanceof Grade => $this->onGradeRecorded($model),
            $model instanceof Certificate => $this->onCertificateIssued($model),
            $model instanceof CourseInvitation => $this->onCourseInvitationCreated($model),
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
     * A notice was posted: tell the people it was addressed to.
     *
     * A course announcement reaches that course's students; an administrator's
     * institution-wide one reaches everybody. The author is never told about
     * their own notice.
     *
     * Note the asymmetry with onAnnouncementCommentCreated below, which is
     * deliberate: a comment tells one person, a notice tells a class.
     */
    private function onAnnouncementCreated(Announcement $announcement): void
    {
        $announcement->loadMissing(['course', 'author']);

        $link = route('announcements.index').'#announcement-'.$announcement->id;
        $author = $announcement->author?->name ?? 'An administrator';

        if ($announcement->course === null) {
            $recipients = User::query()->whereKeyNot($announcement->author_id)->pluck('id');
            $message = "{$author} posted an announcement for everyone";
        } else {
            $recipients = $announcement->course->students()->pluck('users.id');
            $message = "{$author} posted an announcement in {$announcement->course->code}";
        }

        foreach ($recipients as $userId) {
            if ($userId === $announcement->author_id) {
                continue;
            }

            $this->notify(
                userId: $userId,
                type: self::TYPE_ANNOUNCEMENT_POSTED,
                message: $message,
                link: $link,
                // One notice, one telling -- even if this somehow runs twice.
                reference: 'announcement:'.$announcement->id,
            );
        }
    }

    /**
     * Work was marked: tell the student.
     *
     * Only coursework. A quiz is marked the instant it is submitted and the
     * student is looking at the result already, so telling them is noise --
     * and this system's own rule elsewhere is that reminding somebody of a
     * thing they just did is how people learn to ignore notifications.
     * A submission is different: it is marked by a person, later, out of sight.
     */
    private function onGradeRecorded(Grade $grade): void
    {
        if ($grade->submission_id === null) {
            return;
        }

        $grade->loadMissing('submission.assignment.course');

        $submission = $grade->submission;
        $assignment = $submission?->assignment;

        if ($submission === null || $assignment === null) {
            return;
        }

        $this->notify(
            userId: $submission->student_id,
            type: self::TYPE_GRADE_RECORDED,
            message: "Your work on \"{$assignment->title}\" has been marked: ".$grade->display(),
            link: route('assignments.show', $assignment->id),
            reference: 'grade:'.$grade->id,
        );
    }

    /**
     * A credential was minted: tell the holder.
     *
     * The single most significant thing that happens to a student in this
     * system, and until now it happened in silence -- they found out only by
     * visiting My Certificates on the off-chance.
     *
     * The CredentialAuthority does not know this observer exists. It mints the
     * row; Eloquent does the rest.
     */
    private function onCertificateIssued(Certificate $certificate): void
    {
        $certificate->loadMissing(['course', 'learningPath']);

        $subject = $certificate->course?->title
            ?? $certificate->learningPath?->title
            ?? 'your studies';

        $this->notify(
            userId: $certificate->student_id,
            type: self::TYPE_CERTIFICATE_ISSUED,
            message: "You have earned a certificate for {$subject} — credential {$certificate->credential_id}",
            link: route('certificates.show', $certificate->id),
            reference: 'certificate:'.$certificate->id,
        );
    }

    /**
     * A lecturer invited a student to a course: tell them it is waiting.
     *
     * An invitation the student never notices is an invitation that never
     * happened -- it sat on their Courses page for whenever they thought to
     * look.
     */
    private function onCourseInvitationCreated(CourseInvitation $invitation): void
    {
        $invitation->loadMissing('course');

        $course = $invitation->course;

        if ($course === null) {
            return;
        }

        $this->notify(
            userId: $invitation->student_id,
            type: self::TYPE_COURSE_INVITATION,
            message: "You have been invited to join {$course->label()}",
            link: route('courses.index'),
            reference: 'course_invitation:'.$invitation->id,
        );
    }

    /**
     * Hand the row to Module 3's shared sender, which applies the recipient's
     * notification preferences.
     *
     * A reference is optional. Most observed events happen once, so there is
     * nothing to deduplicate; the ones that address a whole class pass one
     * anyway, so that a re-run can never tell the same person twice.
     */
    private function notify(
        int $userId,
        string $type,
        string $message,
        string $link,
        ?string $reference = null
    ): void {
        Notifier::send($userId, $type, $message, $link, $reference);
    }
}
