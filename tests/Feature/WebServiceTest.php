<?php

/**
 * LearnSync -- Automated test
 *
 * Shared: project-wide infrastructure
 *
 * @author Serena Lim Sze Kee, Foo Chong Xian, Ong Shun Yan, Wong Siew Lam, Ong Kwong Wei
 */

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Course;
use App\Models\Grade;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Patterns\Facade\CredentialAuthority;
use App\Support\Api\CourseInfoClient;
use App\Support\Api\CredentialStatusClient;
use App\Support\Api\QuizResultClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http as HttpClient;
use Tests\TestCase;

/**
 * The module-to-module web services, both halves.
 *
 * EXPOSURE is tested by calling each endpoint over HTTP and checking the
 * answer obeys the Interface Agreement: a status of S, F or E, a timeStamp,
 * and the requestID echoed back.
 *
 * CONSUMPTION is tested by driving the client classes, which issue real HTTP
 * requests back into the same application, so the whole round trip is
 * exercised rather than a mocked reply.
 */
class WebServiceTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'learnsync-local-development-key';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.internal_api.key' => self::KEY,
            'services.internal_api.base_url' => 'http://localhost/api',
        ]);
    }

    /**
     * Send the clients' outgoing HTTP calls into this application.
     *
     * A test run has no web server listening, so a client calling
     * http://localhost/api would simply fail to connect. This intercepts the
     * outgoing call and hands it to the test kernel instead, which means the
     * REAL controller, the REAL middleware and the REAL database all take
     * part. Only the network hop is removed, so the round trip is genuine
     * rather than a canned reply.
     */
    private function routeOutgoingCallsIntoTheApplication(): void
    {
        HttpClient::fake(function (ClientRequest $request) {
            $parts = parse_url($request->url());
            $path = $parts['path'] ?? '/';
            $query = $parts['query'] ?? '';

            // Carry the caller's headers through, so the API key check is
            // exercised exactly as it would be over a real connection.
            $headers = [];
            foreach ($request->headers() as $name => $values) {
                $headers[$name] = is_array($values) ? ($values[0] ?? '') : $values;
            }

            $response = $request->method() === 'POST'
                ? $this->withHeaders($headers)->postJson($path, $request->data())
                : $this->withHeaders($headers)->getJson($path.($query !== '' ? '?'.$query : ''));

            return HttpClient::response(
                $response->getContent(),
                $response->getStatusCode(),
                ['Content-Type' => 'application/json']
            );
        });
    }

    /* ------------------------------------------------------------------ *
     * The IFA contract itself
     * ------------------------------------------------------------------ */

    public function test_every_response_carries_status_timestamp_and_the_request_id(): void
    {
        [$course] = $this->courseWithStudent();

        $response = $this->withHeader('X-API-Key', self::KEY)
            ->getJson('/api/courses/info?'.http_build_query([
                'courseId' => $course->id,
                'queryFlag' => 1,
                'requestID' => 'CRS-REQ-TEST01',
                'timeStamp' => now()->utc()->format('Y-m-d\TH:i:s\Z'),
            ]));

        $response->assertOk()
            ->assertJsonPath('status', 'S')
            ->assertJsonPath('data.requestID', 'CRS-REQ-TEST01')
            ->assertJsonStructure(['status', 'timeStamp', 'data']);
    }

    public function test_a_request_without_the_mandatory_ifa_fields_is_refused(): void
    {
        [$course] = $this->courseWithStudent();

        // No requestID and no timeStamp.
        $this->withHeader('X-API-Key', self::KEY)
            ->getJson('/api/courses/info?courseId='.$course->id.'&queryFlag=1')
            ->assertStatus(400)
            ->assertJsonPath('status', 'F');
    }

    public function test_an_internal_service_refuses_a_caller_with_no_api_key(): void
    {
        [$course] = $this->courseWithStudent();

        $this->getJson('/api/courses/info?'.http_build_query([
            'courseId' => $course->id,
            'queryFlag' => 1,
            'requestID' => 'CRS-REQ-TEST02',
            'timeStamp' => now()->utc()->format('Y-m-d\TH:i:s\Z'),
        ]))->assertStatus(401)->assertJsonPath('status', 'F');
    }

    public function test_an_internal_service_refuses_a_wrong_api_key(): void
    {
        [$course] = $this->courseWithStudent();

        $this->withHeader('X-API-Key', 'not-the-right-key')
            ->getJson('/api/courses/info?'.http_build_query([
                'courseId' => $course->id,
                'queryFlag' => 1,
                'requestID' => 'CRS-REQ-TEST03',
                'timeStamp' => now()->utc()->format('Y-m-d\TH:i:s\Z'),
            ]))->assertStatus(401);
    }

    /* ------------------------------------------------------------------ *
     * Module 1 exposes getCredentialStatus
     * ------------------------------------------------------------------ */

    public function test_the_credential_service_is_public_and_needs_no_key(): void
    {
        $certificate = $this->issuedCertificate();

        // No X-API-Key header at all. Section 7 requires this route to be open.
        $this->getJson('/api/credentials/verify?'.http_build_query([
            'credentialId' => $certificate->credential_id,
            'detailFlag' => 1,
            'requestID' => 'CRED-REQ-TEST01',
            'timeStamp' => now()->utc()->format('Y-m-d\TH:i:s\Z'),
        ]))->assertOk()
            ->assertJsonPath('status', 'S')
            ->assertJsonPath('data.credentialStatus', 'VALID');
    }

    public function test_detail_flag_one_does_not_disclose_the_holder(): void
    {
        $certificate = $this->issuedCertificate();

        $this->getJson('/api/credentials/verify?'.http_build_query([
            'credentialId' => $certificate->credential_id,
            'detailFlag' => 1,
            'requestID' => 'CRED-REQ-TEST02',
            'timeStamp' => now()->utc()->format('Y-m-d\TH:i:s\Z'),
        ]))->assertOk()->assertJsonMissingPath('data.holderName');
    }

    public function test_detail_flag_two_returns_the_holder(): void
    {
        $certificate = $this->issuedCertificate();

        $this->getJson('/api/credentials/verify?'.http_build_query([
            'credentialId' => $certificate->credential_id,
            'detailFlag' => 2,
            'requestID' => 'CRED-REQ-TEST03',
            'timeStamp' => now()->utc()->format('Y-m-d\TH:i:s\Z'),
        ]))->assertOk()->assertJsonPath('data.holderName', $certificate->student->name);
    }

    public function test_an_unknown_credential_reports_not_found_rather_than_erroring(): void
    {
        $this->getJson('/api/credentials/verify?'.http_build_query([
            'credentialId' => 'LS-2026-ZZZZZZZZ',
            'detailFlag' => 1,
            'requestID' => 'CRED-REQ-TEST04',
            'timeStamp' => now()->utc()->format('Y-m-d\TH:i:s\Z'),
        ]))->assertOk()->assertJsonPath('data.credentialStatus', 'NOT_FOUND');
    }

    public function test_a_tampered_credential_is_reported_over_the_service(): void
    {
        $certificate = $this->issuedCertificate();

        // Change the mark straight in the database, as an attacker would.
        $certificate->update(['final_score' => 99.9]);

        $this->getJson('/api/credentials/verify?'.http_build_query([
            'credentialId' => $certificate->credential_id,
            'detailFlag' => 1,
            'requestID' => 'CRED-REQ-TEST05',
            'timeStamp' => now()->utc()->format('Y-m-d\TH:i:s\Z'),
        ]))->assertOk()->assertJsonPath('data.credentialStatus', 'TAMPERED');
    }

    /* ------------------------------------------------------------------ *
     * Module 4 exposes getQuizResult, Module 5 exposes getCourseAnalytics
     * ------------------------------------------------------------------ */

    public function test_the_quiz_service_returns_the_best_attempt(): void
    {
        [$course, $student] = $this->courseWithStudent();
        $quiz = $this->quizWithGrades($course, $student, [55.0, 88.0, 61.0]);

        $this->withHeader('X-API-Key', self::KEY)
            ->getJson('/api/quizzes/result?'.http_build_query([
                'quizId' => $quiz->id,
                'studentId' => $student->id,
                'requestID' => 'QUZ-REQ-TEST01',
                'timeStamp' => now()->utc()->format('Y-m-d\TH:i:s\Z'),
            ]))->assertOk()
            ->assertJsonPath('data.bestScore', fn ($v) => (float) $v === 88.0)
            ->assertJsonPath('data.attemptCount', 3)
            ->assertJsonPath('data.passed', true);
    }

    public function test_the_analytics_service_returns_cohort_figures_only(): void
    {
        [$course, $student] = $this->courseWithStudent();
        $this->quizWithGrades($course, $student, [70.0, 90.0]);

        $response = $this->withHeader('X-API-Key', self::KEY)
            ->getJson('/api/analytics/course?'.http_build_query([
                'courseId' => $course->id,
                'requestID' => 'ANL-REQ-TEST01',
                'timeStamp' => now()->utc()->format('Y-m-d\TH:i:s\Z'),
            ]));

        $response->assertOk()
            ->assertJsonPath('data.courseCode', $course->code)
            ->assertJsonPath('data.gradedCount', 2)
            ->assertJsonPath('data.averageScore', fn ($v) => (float) $v === 80.0);

        // No student is ever named by this service.
        $this->assertStringNotContainsString($student->name, $response->getContent());
    }

    /* ------------------------------------------------------------------ *
     * Module 3 exposes sendNotification
     * ------------------------------------------------------------------ */

    public function test_the_notification_service_writes_to_the_inbox(): void
    {
        [, $student] = $this->courseWithStudent();

        $this->withHeader('X-API-Key', self::KEY)
            ->postJson('/api/notifications/send', [
                'userId' => $student->id,
                'type' => 'grade.recorded',
                'message' => 'Your quiz has been marked.',
                'link' => 'http://localhost/dashboard',
                'reference' => 'quiz:1',
                'requestID' => 'NTF-REQ-TEST01',
                'timeStamp' => now()->utc()->format('Y-m-d\TH:i:s\Z'),
            ])->assertOk()->assertJsonPath('data.delivered', true);

        $this->assertSame(1, $student->notifications()->count());
    }

    public function test_the_notification_service_refuses_a_type_outside_the_allow_list(): void
    {
        [, $student] = $this->courseWithStudent();

        $this->withHeader('X-API-Key', self::KEY)
            ->postJson('/api/notifications/send', [
                'userId' => $student->id,
                'type' => 'anything.i.like',
                'message' => 'A type nobody can switch off.',
                'requestID' => 'NTF-REQ-TEST02',
                'timeStamp' => now()->utc()->format('Y-m-d\TH:i:s\Z'),
            ])->assertStatus(400)->assertJsonPath('status', 'F');

        $this->assertSame(0, $student->notifications()->count());
    }

    /* ------------------------------------------------------------------ *
     * CONSUMPTION: the client classes calling real services
     * ------------------------------------------------------------------ */

    public function test_module_1_consumes_module_2s_course_info_service(): void
    {
        $this->routeOutgoingCallsIntoTheApplication();

        [$course] = $this->courseWithStudent();

        $data = app(CourseInfoClient::class)->fetch($course->id);

        $this->assertNotNull($data, 'The course info service should have answered.');
        $this->assertSame($course->code, $data['courseCode']);
        $this->assertSame($course->title, $data['courseTitle']);
    }

    public function test_module_5_consumes_module_1s_credential_service(): void
    {
        $this->routeOutgoingCallsIntoTheApplication();

        $certificate = $this->issuedCertificate();

        $client = app(CredentialStatusClient::class);

        $this->assertTrue($client->isValid($certificate->credential_id));
        $this->assertFalse($client->isValid('LS-2026-ZZZZZZZZ'));
    }

    public function test_module_3_consumes_module_4s_quiz_result_service(): void
    {
        $this->routeOutgoingCallsIntoTheApplication();

        [$course, $student] = $this->courseWithStudent();
        $quiz = $this->quizWithGrades($course, $student, [82.0]);

        $data = app(QuizResultClient::class)->fetch($quiz->id, $student->id);

        $this->assertNotNull($data);
        $this->assertTrue($data['graded']);
        $this->assertEquals(82.0, $data['bestScore']);
        $this->assertSame('A', $data['letterGrade']);
    }

    public function test_a_client_returns_null_rather_than_throwing_when_the_service_is_unreachable(): void
    {
        // Point the clients at a host that is not listening.
        config(['services.internal_api.base_url' => 'http://127.0.0.1:9/api']);
        config(['services.internal_api.timeout' => 1]);

        $this->assertNull(app(CourseInfoClient::class)->fetch(1));
    }

    /* ------------------------------------------------------------------ */

    /**
     * @return array{0: Course, 1: User}
     */
    private function courseWithStudent(): array
    {
        $lecturer = User::factory()->create(['role' => 'instructor']);
        $student = User::factory()->create(['role' => 'student']);

        $course = Course::create([
            'instructor_id' => $lecturer->id,
            'code' => 'BMIT3173',
            'title' => 'Integrative Programming',
            'description' => 'A course used by the web service tests.',
        ]);

        $course->students()->attach($student->id);

        return [$course, $student];
    }

    /**
     * @param  array<int, float>  $scores
     */
    private function quizWithGrades(Course $course, User $student, array $scores): Quiz
    {
        $quiz = Quiz::create([
            'course_id' => $course->id,
            'title' => 'Web Services Quiz',
            'time_limit' => 15,
        ]);

        Question::create([
            'quiz_id' => $quiz->id,
            'type' => 'mcq',
            'question_text' => 'A question.',
        ]);

        foreach ($scores as $score) {
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

        return $quiz;
    }

    private function issuedCertificate(): Certificate
    {
        [$course, $student] = $this->courseWithStudent();

        CertificateTemplate::create([
            'name' => 'Default',
            'body_text' => 'Awarded to {{student_name}} for {{course_title}}.',
            'is_active' => true,
        ]);

        return app(CredentialAuthority::class)->issueCertificate($student, $course, 88.0);
    }
}
