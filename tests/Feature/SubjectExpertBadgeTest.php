<?php

namespace Tests\Feature;

use App\Models\Badge;
use App\Models\Course;
use App\Models\Grade;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Patterns\Facade\CredentialAuthority;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Subject Expert" -- every quiz in one subject, passed.
 *
 * The award runs through the ordinary badge registry rather than special-cased
 * code: a row with criteria_type `all_quizzes_in_course` scoped to a course,
 * evaluated by BadgeRuleEvaluator like every other rule.
 *
 * The completion condition, settled per the task's recommended defaults:
 *   - "finished" means PASSED (>= the academic pass mark), not merely attempted
 *   - a quiz added afterwards does not revoke an award already made
 *   - a subject with no quizzes awards nothing
 */
class SubjectExpertBadgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_passing_every_quiz_in_the_subject_awards_the_badge(): void
    {
        [$course, $student, $badge] = $this->subjectWithBadge(quizCount: 2);

        $this->pass($student, $course->quizzes[0]);
        // One of two is not "every".
        $this->assertFalse($this->holds($student, $badge));

        $this->pass($student, $course->quizzes[1]);
        $this->assertTrue($this->holds($student, $badge));
    }

    public function test_attempting_without_passing_does_not_award_it(): void
    {
        [$course, $student, $badge] = $this->subjectWithBadge(quizCount: 1);

        // Sat, and failed. Attempting is not expertise.
        $this->grade($student, $course->quizzes[0], 20.0);

        $this->assertFalse($this->holds($student, $badge));
    }

    public function test_a_subject_with_no_quizzes_awards_nothing(): void
    {
        [, $student, $badge] = $this->subjectWithBadge(quizCount: 0);

        $this->authority()->evaluateBadges($student);

        $this->assertFalse($this->holds($student, $badge));
    }

    public function test_resubmitting_does_not_award_a_second_copy(): void
    {
        [$course, $student, $badge] = $this->subjectWithBadge(quizCount: 1);

        $this->pass($student, $course->quizzes[0]);
        $this->pass($student, $course->quizzes[0]);
        $this->authority()->evaluateBadges($student);

        $this->assertSame(1, $student->badges()->where('badges.id', $badge->id)->count());
    }

    public function test_a_quiz_added_afterwards_does_not_revoke_the_badge(): void
    {
        [$course, $student, $badge] = $this->subjectWithBadge(quizCount: 1);

        $this->pass($student, $course->quizzes[0]);
        $this->assertTrue($this->holds($student, $badge));

        // The syllabus grows. What was already earned stays earned.
        $this->makeQuiz($course, 'Added later');
        $this->authority()->evaluateBadges($student);

        $this->assertTrue($this->holds($student, $badge));
    }

    public function test_the_badge_is_scoped_to_its_own_subject(): void
    {
        [$course, $student, $badge] = $this->subjectWithBadge(quizCount: 1);

        // A different subject, with its own badge and its own quiz.
        $other = $this->makeCourse('BMIT8888', 'Another Subject');
        $otherQuiz = $this->makeQuiz($other, 'Unrelated');
        $otherBadge = $this->makeBadge($other, 'Subject Expert — Another Subject');
        $other->students()->attach($student->id);

        $this->pass($student, $course->quizzes[0]);

        // Clearing one subject must not hand over the other subject's badge.
        $this->assertTrue($this->holds($student, $badge));
        $this->assertFalse($this->holds($student, $otherBadge));

        $this->pass($student, $otherQuiz);
        $this->assertTrue($this->holds($student, $otherBadge));
    }

    /* ---------------------------------------------------------------- */

    /**
     * @return array{0: Course, 1: User, 2: Badge}
     */
    private function subjectWithBadge(int $quizCount): array
    {
        $course = $this->makeCourse('BMIT7777', 'Integrative Programming');
        $student = User::factory()->create(['role' => 'student']);
        $course->students()->attach($student->id);

        for ($i = 1; $i <= $quizCount; $i++) {
            $this->makeQuiz($course, "Quiz {$i}");
        }

        $badge = $this->makeBadge($course, 'Subject Expert — Integrative Programming');

        return [$course->load('quizzes'), $student, $badge];
    }

    private function makeCourse(string $code, string $title): Course
    {
        $lecturer = User::factory()->create(['role' => 'instructor']);

        return Course::create([
            'instructor_id' => $lecturer->id,
            'code' => $code,
            'title' => $title,
            'description' => 'A subject to be expert in.',
        ]);
    }

    private function makeQuiz(Course $course, string $title): Quiz
    {
        $quiz = Quiz::create([
            'course_id' => $course->id,
            'title' => $title,
            'time_limit' => 10,
        ]);

        Question::create([
            'quiz_id' => $quiz->id,
            'type' => 'mcq',
            'question_text' => 'Does this question exist?',
        ]);

        return $quiz;
    }

    private function makeBadge(Course $course, string $name): Badge
    {
        return Badge::create([
            'name' => $name,
            'description' => 'Pass every quiz in this subject.',
            'tier' => 'silver',
            'criteria_type' => 'all_quizzes_in_course',
            'criteria_value' => 1,
            'course_id' => $course->id,
            'is_active' => true,
        ]);
    }

    /**
     * Sit a quiz and score a pass. Writing the Grade is what wakes the
     * credentialing chain, exactly as QuizAttemptController does.
     */
    private function pass(User $student, Quiz $quiz): void
    {
        $this->grade($student, $quiz, 85.0);
    }

    private function grade(User $student, Quiz $quiz, float $score): void
    {
        $attempt = QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'student_id' => $student->id,
            'duration_seconds' => 60,
        ]);

        Grade::create([
            'quiz_attempt_id' => $attempt->id,
            'calculated_score' => $score,
        ]);
    }

    private function holds(User $student, Badge $badge): bool
    {
        return $student->badges()->where('badges.id', $badge->id)->exists();
    }

    private function authority(): CredentialAuthority
    {
        return app(CredentialAuthority::class);
    }
}
