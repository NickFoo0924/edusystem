<?php

/**
 * LearnSync -- Automated test
 *
 * Module 1: Identity, Access & Digital Credentialing
 *
 * @author Serena Lim Sze Kee
 */

namespace Tests\Feature;

use App\Models\Badge;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
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
 * Award rules an administrator defines, without a developer changing code.
 *
 * The point of these tests is that nothing below constructs a special case: a
 * rule is a row, and the same AwardConditionEvaluator answers it whether it
 * awards a badge or mints a certificate, and whether it was seeded with the
 * application or typed in this morning.
 */
class AwardRuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_defined_average_score_rule_awards_a_badge(): void
    {
        [$course, $student] = $this->subject();
        $this->makeQuiz($course, 'One');
        $this->makeQuiz($course, 'Two');

        $rule = $this->rule($course, [
            'name' => 'Consistent Performer',
            'criteria_type' => 'average_score_in_course',
            'criteria_value' => 75,
        ]);

        // 70 and 74 average 72 -- under the bar.
        $this->grade($student, $course->quizzes[0], 70.0);
        $this->grade($student, $course->quizzes[1], 74.0);
        $this->assertFalse($this->holds($student, $rule));

        // A better re-sit lifts the mean over 75.
        $this->grade($student, $course->quizzes[1], 90.0);
        $this->assertTrue($this->holds($student, $rule));
    }

    public function test_an_admin_defined_quizzes_completed_rule_counts_distinct_quizzes(): void
    {
        [$course, $student] = $this->subject();
        $this->makeQuiz($course, 'One');
        $this->makeQuiz($course, 'Two');

        $rule = $this->rule(null, [
            'name' => 'Quiz Marathon',
            'criteria_type' => 'quizzes_completed',
            'criteria_value' => 2,
        ]);

        // Re-sitting the SAME quiz is one quiz completed, not two.
        $this->grade($student, $course->quizzes[0], 80.0);
        $this->grade($student, $course->quizzes[0], 85.0);
        $this->assertFalse($this->holds($student, $rule));

        $this->grade($student, $course->quizzes[1], 80.0);
        $this->assertTrue($this->holds($student, $rule));
    }

    public function test_an_admin_defined_certificate_rule_mints_a_real_credential(): void
    {
        [$course, $student] = $this->subject();
        $this->makeQuiz($course, 'One');
        CertificateTemplate::create([
            'name' => 'Default',
            'body_text' => 'Awarded to {{student_name}} for {{course_title}}.',
            'is_active' => true,
        ]);

        $this->rule($course, [
            'name' => 'Distinction in this subject',
            'award_type' => 'certificate',
            'criteria_type' => 'average_score_in_course',
            'criteria_value' => 80,
        ]);

        $this->assertSame(0, Certificate::where('student_id', $student->id)->count());

        $this->grade($student, $course->quizzes[0], 92.0);

        $certificate = Certificate::where('student_id', $student->id)->first();

        $this->assertNotNull($certificate, 'The admin-defined rule should have minted a credential.');
        // A real credential, not a stub row: verifiable through the same path
        // an automatic issuance produces.
        $this->assertMatchesRegularExpression('/^LS-\d{4}-[0-9A-Z]{8}$/', $certificate->credential_id);
        $this->assertSame('valid', $this->authority()->verify($certificate->credential_id)['status']);
    }

    public function test_a_certificate_rule_does_not_mint_twice(): void
    {
        [$course, $student] = $this->subject();
        $this->makeQuiz($course, 'One');
        CertificateTemplate::create([
            'name' => 'Default',
            'body_text' => 'Awarded to {{student_name}}.',
            'is_active' => true,
        ]);

        $this->rule($course, [
            'name' => 'Distinction',
            'award_type' => 'certificate',
            'criteria_type' => 'average_score_in_course',
            'criteria_value' => 80,
        ]);

        $this->grade($student, $course->quizzes[0], 92.0);
        $this->grade($student, $course->quizzes[0], 95.0);

        $this->assertSame(1, Certificate::where('student_id', $student->id)->whereNull('revoked_at')->count());
    }

    public function test_a_certificate_rule_is_never_handed_out_as_a_badge(): void
    {
        [$course, $student] = $this->subject();
        $this->makeQuiz($course, 'One');
        CertificateTemplate::create([
            'name' => 'Default',
            'body_text' => 'Awarded to {{student_name}}.',
            'is_active' => true,
        ]);

        $rule = $this->rule($course, [
            'name' => 'Distinction',
            'award_type' => 'certificate',
            'criteria_type' => 'average_score_in_course',
            'criteria_value' => 80,
        ]);

        $this->grade($student, $course->quizzes[0], 92.0);

        // It minted a credential, so it must NOT also sit in the cabinet.
        $this->assertFalse($this->holds($student, $rule));
    }

    public function test_deactivating_a_rule_stops_it_without_removing_awards_already_made(): void
    {
        [$course, $student] = $this->subject();
        $this->makeQuiz($course, 'One');

        $rule = $this->rule($course, [
            'name' => 'Subject Expert',
            'criteria_type' => 'all_quizzes_in_course',
            'criteria_value' => 1,
        ]);

        $this->grade($student, $course->quizzes[0], 85.0);
        $this->assertTrue($this->holds($student, $rule));

        $rule->update(['is_active' => false]);

        // The award already made is retained, by design.
        $this->assertTrue($this->holds($student, $rule));

        /*
         * The evaluator reads the rule registry once per instance, on purpose,
         * so a rule cannot change underneath a student midway through their
         * awards. That cache is request-scoped, so an administrator's edit
         * takes effect on the NEXT request rather than instantly within the
         * current one -- which in a web application is every request but the
         * one the admin themselves is making.
         *
         * A test runs in a single process, so the scoped instance has to be
         * discarded here to stand where the next request would.
         */
        $this->app->forgetScopedInstances();

        // A second student can no longer earn it.
        $other = User::factory()->create(['role' => 'student']);
        $course->students()->attach($other->id);
        $this->grade($other, $course->quizzes[0], 85.0);

        $this->assertFalse($this->holds($other, $rule));
    }

    /* ---------------------------------------------------------------- */

    /**
     * @return array{0: Course, 1: User}
     */
    private function subject(): array
    {
        $lecturer = User::factory()->create(['role' => 'instructor']);
        $student = User::factory()->create(['role' => 'student']);

        $course = Course::create([
            'instructor_id' => $lecturer->id,
            'code' => 'BMIT5555',
            'title' => 'Configurable Awards',
            'description' => 'A subject for testing rules.',
        ]);

        $course->students()->attach($student->id);

        return [$course, $student];
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
            'question_text' => 'A question.',
        ]);

        $course->load('quizzes');

        return $quiz;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function rule(?Course $course, array $attributes): Badge
    {
        return Badge::create(array_merge([
            'description' => 'A rule an administrator defined.',
            'award_type' => 'badge',
            'tier' => 'silver',
            'course_id' => $course?->id,
            'is_active' => true,
        ], $attributes));
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

    private function holds(User $student, Badge $rule): bool
    {
        return $student->badges()->where('badges.id', $rule->id)->exists();
    }

    private function authority(): CredentialAuthority
    {
        return app(CredentialAuthority::class);
    }
}
