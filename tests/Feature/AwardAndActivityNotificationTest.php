<?php

/**
 * LearnSync -- Automated test
 *
 * Module 3: Student Forum & Notifications
 *
 * @author Ong Shun Yan
 */

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\Badge;
use App\Models\CertificateTemplate;
use App\Models\Course;
use App\Models\CourseInvitation;
use App\Models\Grade;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Submission;
use App\Models\User;
use App\Patterns\Facade\Subsystem\BadgeRuleEvaluator;
use App\Patterns\Observer\SystemNotificationObserver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The five silences found by the integration audit, closed.
 *
 * An audit of the module integrations recorded that the most significant events in
 * the system happened without telling anybody: a certificate minted, a badge
 * awarded, work marked, a notice posted, a course invitation issued. The
 * delivery path existed and was simply never called.
 *
 * Every case below goes through Notifier::send(), so each one also honours the
 * recipient's preferences and refuses to say the same thing twice.
 */
class AwardAndActivityNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_earning_a_certificate_notifies_the_holder(): void
    {
        [$course, $student] = $this->subject();
        $this->template();

        app(\App\Patterns\Facade\CredentialAuthority::class)
            ->issueCertificate($student, $course, 88.0);

        $this->assertNotified($student, SystemNotificationObserver::TYPE_CERTIFICATE_ISSUED);
    }

    public function test_earning_a_badge_notifies_the_student(): void
    {
        [$course, $student] = $this->subject();
        $quiz = $this->quiz($course);

        Badge::create([
            'name' => 'Subject Expert',
            'description' => 'Pass every quiz in this subject.',
            'award_type' => 'badge',
            'tier' => 'gold',
            'criteria_type' => 'all_quizzes_in_course',
            'criteria_value' => 1,
            'course_id' => $course->id,
            'is_active' => true,
        ]);

        $this->gradeQuiz($student, $quiz, 90.0);

        $this->assertNotified($student, BadgeRuleEvaluator::TYPE_BADGE_AWARDED);
    }

    public function test_marking_submitted_work_notifies_the_student(): void
    {
        [$course, $student] = $this->subject();

        $assignment = Assignment::create([
            'course_id' => $course->id,
            'title' => 'Report One',
            'description' => 'Write it up.',
            'due_date' => now()->addWeek(),
        ]);

        $submission = Submission::create([
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'state' => 'submitted',
            'submitted_at' => now(),
        ]);

        Grade::create(['submission_id' => $submission->id, 'calculated_score' => 72.0]);

        $this->assertNotified($student, SystemNotificationObserver::TYPE_GRADE_RECORDED);
    }

    public function test_a_marked_quiz_does_not_notify_because_the_result_is_already_on_screen(): void
    {
        [$course, $student] = $this->subject();
        $quiz = $this->quiz($course);

        $this->gradeQuiz($student, $quiz, 90.0);

        $this->assertSame(
            0,
            Notification::where('user_id', $student->id)
                ->where('type', SystemNotificationObserver::TYPE_GRADE_RECORDED)
                ->count()
        );
    }

    public function test_posting_an_announcement_notifies_the_course(): void
    {
        [$course, $student, $lecturer] = $this->subject();

        Announcement::create([
            'course_id' => $course->id,
            'author_id' => $lecturer->id,
            'content' => 'Class is moved to Thursday.',
        ]);

        $this->assertNotified($student, SystemNotificationObserver::TYPE_ANNOUNCEMENT_POSTED);

        // The author is not told about their own notice.
        $this->assertSame(
            0,
            Notification::where('user_id', $lecturer->id)
                ->where('type', SystemNotificationObserver::TYPE_ANNOUNCEMENT_POSTED)
                ->count()
        );
    }

    public function test_inviting_a_student_to_a_course_notifies_them(): void
    {
        [$course, , $lecturer] = $this->subject();
        $invitee = User::factory()->create(['role' => 'student']);

        CourseInvitation::create([
            'course_id' => $course->id,
            'student_id' => $invitee->id,
            'invited_by' => $lecturer->id,
        ]);

        $this->assertNotified($invitee, SystemNotificationObserver::TYPE_COURSE_INVITATION);
    }

    public function test_a_switched_off_preference_still_suppresses_the_new_types(): void
    {
        [$course, $student, $lecturer] = $this->subject();

        NotificationPreference::create([
            'user_id' => $student->id,
            'type' => SystemNotificationObserver::TYPE_ANNOUNCEMENT_POSTED,
            'enabled' => false,
        ]);

        Announcement::create([
            'course_id' => $course->id,
            'author_id' => $lecturer->id,
            'content' => 'You asked not to hear about this.',
        ]);

        $this->assertSame(
            0,
            Notification::where('user_id', $student->id)
                ->where('type', SystemNotificationObserver::TYPE_ANNOUNCEMENT_POSTED)
                ->count()
        );
    }

    /* ---------------------------------------------------------------- */

    /**
     * @return array{0: Course, 1: User, 2: User}
     */
    private function subject(): array
    {
        $lecturer = User::factory()->create(['role' => 'instructor']);
        $student = User::factory()->create(['role' => 'student']);

        $course = Course::create([
            'instructor_id' => $lecturer->id,
            'code' => 'BMIT6666',
            'title' => 'Notification Testing',
            'description' => 'A course that tells people things.',
        ]);

        $course->students()->attach($student->id);

        return [$course, $student, $lecturer];
    }

    private function quiz(Course $course): Quiz
    {
        $quiz = Quiz::create([
            'course_id' => $course->id,
            'title' => 'The only quiz',
            'time_limit' => 10,
        ]);

        Question::create([
            'quiz_id' => $quiz->id,
            'type' => 'mcq',
            'question_text' => 'A question.',
        ]);

        return $quiz;
    }

    private function gradeQuiz(User $student, Quiz $quiz, float $score): void
    {
        $attempt = QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'student_id' => $student->id,
            'duration_seconds' => 30,
        ]);

        Grade::create(['quiz_attempt_id' => $attempt->id, 'calculated_score' => $score]);
    }

    private function template(): CertificateTemplate
    {
        return CertificateTemplate::create([
            'name' => 'Default',
            'body_text' => 'Awarded to {{student_name}} for {{course_title}}.',
            'is_active' => true,
        ]);
    }

    private function assertNotified(User $user, string $type): void
    {
        $this->assertGreaterThan(
            0,
            Notification::where('user_id', $user->id)->where('type', $type)->count(),
            "Expected {$user->name} to have been notified of \"{$type}\", but nothing was written."
        );
    }
}
