<?php

namespace Database\Seeders;

use App\Models\Answer;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\Grade;
use App\Models\Post;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Reply;
use App\Models\Submission;
use App\Models\User;
use App\Patterns\Strategy\GradingStrategyResolver;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * A realistic cohort: 50 students with individual histories spread over a term.
 *
 * Run separately from DatabaseSeeder so the default seed stays fast:
 *
 *     php artisan db:seed --class=BulkStudentSeeder
 *
 * Nothing here writes rows the application would not have written itself. Quiz
 * answers are marked by the real grading Strategy, submissions move through the
 * real State pattern, and the Grade write wakes the CredentialAuthority exactly
 * as it does in the running system -- so the progress, badges and certificates
 * this produces are genuine consequences of the data, not fabricated rows.
 *
 * History is generated on a simulated clock (Carbon::setTestNow), which is what
 * gives every record a plausible date: progress snapshots form a real curve on
 * the dashboard chart, and the activity log reads like a term's worth of use.
 */
class BulkStudentSeeder extends Seeder
{
    private const STUDENT_COUNT = 50;

    /**
     * How far back the simulated term starts.
     */
    private const TERM_WEEKS = 14;

    /**
     * Fixed so the demo cohort is identical on every reseed. A marker seeing
     * the same numbers twice is a feature.
     */
    private const RANDOM_SEED = 20260816;

    /**
     * The real clock, captured before anything freezes it. Nothing generated
     * here may be dated later than this -- a graded submission in the future
     * is not history.
     */
    private Carbon $realNow;

    public function run(): void
    {
        mt_srand(self::RANDOM_SEED);

        $this->realNow = Carbon::now();
        $termStart = $this->realNow->copy()->subWeeks(self::TERM_WEEKS)->startOfDay();

        $this->command->info('Creating assessments for courses that have none...');
        $this->seedAssessments($termStart);

        $this->command->info('Creating '.self::STUDENT_COUNT.' students...');
        $students = $this->seedStudents();

        $this->command->info('Generating coursework history (this renders certificate PDFs, so it takes a minute)...');
        $this->seedHistory($students, $termStart);

        // Never leave the clock frozen -- everything afterwards would inherit it.
        Carbon::setTestNow();

        $this->report();
    }

    /**
     * Give every course a quiz and two assignments, so students spread across
     * six courses all have something to have done.
     */
    private function seedAssessments(Carbon $termStart): void
    {
        $questionBank = [
            'BMCS3404' => [
                ['mcq', 'Which project phase produces the requirements specification?',
                    ['Analysis', 'Deployment', 'Maintenance', 'Coding']],
                ['text', 'What is the name for a scheduled point at which progress is formally reviewed?',
                    ['milestone', 'milestones']],
                ['mcq', 'Which document defines a project\'s objectives, scope and deliverables at the outset?',
                    ['Project charter', 'Gantt chart', 'Risk register', 'Test plan']],
                ['text', 'What name is given to uncontrolled growth in a project\'s requirements after work has begun?',
                    ['scope creep', 'requirement creep']],
            ],
            'BMSE3153' => [
                ['mcq', 'Which development approach delivers working software in short repeated cycles?',
                    ['Agile', 'Waterfall', 'V-model', 'Big bang']],
                ['text', 'What document records identified risks together with their likelihood and mitigation?',
                    ['risk register', 'risk log']],
                ['mcq', 'In a Gantt chart, what does the critical path represent?',
                    ['The longest sequence of dependent tasks', 'The most expensive tasks',
                        'Tasks assigned to the most people', 'Tasks with the highest risk']],
                ['text', 'Which estimation technique asks experts to estimate anonymously over several rounds until they converge?',
                    ['delphi', 'delphi technique', 'wideband delphi']],
            ],
            'BMIT3173' => [],
            'BMIT3123' => [
                ['mcq', 'What is the first phase of a penetration test?',
                    ['Reconnaissance', 'Exploitation', 'Reporting', 'Privilege escalation']],
                ['text', 'What identifier is used to catalogue a publicly known security vulnerability?',
                    ['CVE', 'common vulnerabilities and exposures']],
                ['mcq', 'Which attack inserts malicious SQL into an input that is concatenated into a query?',
                    ['SQL injection', 'Cross-site scripting', 'Buffer overflow', 'Phishing']],
                ['text', 'What is the term for testing a system with no prior knowledge of its internals?',
                    ['black box', 'black box testing', 'blackbox']],
            ],
            'BMIT3113' => [
                ['mcq', 'Which file stores local user account information on a Linux system?',
                    ['/etc/passwd', '/etc/hosts', '/var/log/syslog', '/etc/fstab']],
                ['text', 'Which service keeps server clocks synchronised across a network?',
                    ['NTP', 'network time protocol']],
                ['mcq', 'Which Linux command changes the permissions on a file?',
                    ['chmod', 'chown', 'chgrp', 'chroot']],
                ['text', 'What is the name of the backup strategy that keeps three copies on two media with one offsite?',
                    ['3-2-1', '3-2-1 rule', '321 rule']],
            ],
            'BMIT3084' => [
                ['mcq', 'Which protocol automatically assigns IP addresses to hosts?',
                    ['DHCP', 'DNS', 'ARP', 'ICMP']],
                ['text', 'What is the term for dividing a network into smaller logical networks?',
                    ['subnetting', 'subnet', 'subnetting a network']],
                ['mcq', 'At which OSI layer does a router operate?',
                    ['Layer 3 (Network)', 'Layer 2 (Data Link)', 'Layer 4 (Transport)', 'Layer 1 (Physical)']],
                ['text', 'What technology separates one physical switch into multiple logical broadcast domains?',
                    ['VLAN', 'virtual LAN', 'vlans']],
            ],
        ];

        foreach (Course::all() as $course) {
            if ($course->quizzes()->count() === 0 && ! empty($questionBank[$course->code])) {
                $quiz = Quiz::create([
                    'course_id' => $course->id,
                    'title' => $course->title.' — Progress Test',
                    'time_limit' => 15,
                ]);

                foreach ($questionBank[$course->code] as [$type, $text, $options]) {
                    $question = Question::create([
                        'quiz_id' => $quiz->id,
                        'type' => $type === 'mcq' ? Question::TYPE_MCQ : Question::TYPE_TEXT,
                        'question_text' => $text,
                    ]);

                    foreach ($options as $index => $option) {
                        Answer::create([
                            'question_id' => $question->id,
                            'answer_text' => $option,
                            // MCQ: only the first option is correct.
                            // Text: every listed wording is accepted.
                            'is_correct' => $type === 'mcq' ? $index === 0 : true,
                        ]);
                    }
                }
            }

            // Two assignments per course, due at weeks 6 and 11 of the term, so
            // a diligent student can reach the five on-time submissions the
            // "Always On Time" badge asks for.
            $existing = $course->assignments()->count();

            foreach ([['Coursework 1', 6], ['Coursework 2', 11]] as $index => [$title, $week]) {
                if ($existing > $index) {
                    continue;
                }

                Assignment::create([
                    'course_id' => $course->id,
                    'title' => $course->code.' '.$title,
                    'description' => 'Submit your work as a PDF or zip archive.',
                    'due_date' => $termStart->copy()->addWeeks($week),
                    'allow_late_submission' => true,
                ]);
            }
        }
    }

    /**
     * student1 .. student50, each with a hidden ability score that drives how
     * well they do. Abilities cluster around a passing average rather than
     * spreading uniformly, so the grade distribution looks like a real cohort.
     *
     * @return array<int, array{user: User, ability: int}>
     */
    private function seedStudents(): array
    {
        $students = [];

        for ($i = 1; $i <= self::STUDENT_COUNT; $i++) {
            $students[] = [
                'user' => User::create([
                    'name' => 'student'.$i,
                    'email' => 'student'.$i.'@gmail.com',
                    'password' => 'password',
                    'role' => 'student',
                    'is_active' => true,
                ]),
                'ability' => $this->ability(),
            ];
        }

        return $students;
    }

    /**
     * A rough bell curve: the mean of four rolls clusters near the middle far
     * more often than a single roll would.
     */
    private function ability(): int
    {
        $rolls = 0;
        for ($i = 0; $i < 4; $i++) {
            $rolls += mt_rand(20, 100);
        }

        return (int) max(22, min(98, round($rolls / 4)));
    }

    /**
     * Walk the term day by day for each student, doing what a student does.
     *
     * @param  array<int, array{user: User, ability: int}>  $students
     */
    private function seedHistory(array $students, Carbon $termStart): void
    {
        $courses = Course::with(['quizzes.questions.answers', 'assignments', 'forum'])->get();
        $resolver = new GradingStrategyResolver();

        foreach ($students as $entry) {
            $student = $entry['user'];
            $ability = $entry['ability'];

            // Each student takes between two and four courses.
            $enrolled = $courses->shuffle()->take(mt_rand(2, 4));
            $student->courses()->attach($enrolled->pluck('id'));

            foreach ($enrolled as $course) {
                // A weaker student is likelier to disengage entirely.
                $engagement = min(95, $ability + 15);

                if ($course->forum && mt_rand(1, 100) <= $engagement) {
                    $this->seedForumActivity($student, $course, $termStart);
                }

                foreach ($course->quizzes as $quiz) {
                    if ($quiz->questions->isEmpty() || mt_rand(1, 100) > $engagement) {
                        continue;
                    }

                    $this->seedQuizAttempt($student, $quiz, $ability, $termStart, $resolver);
                }

                foreach ($course->assignments as $assignment) {
                    if (mt_rand(1, 100) > $engagement) {
                        continue;
                    }

                    $this->seedSubmission($student, $assignment, $ability);
                }
            }
        }
    }

    private function seedForumActivity(User $student, Course $course, Carbon $termStart): void
    {
        Carbon::setTestNow($termStart->copy()->addDays(mt_rand(3, 70))->addHours(mt_rand(8, 20)));

        $questions = [
            'Will this be covered in the final assessment?',
            'Could you clarify the requirements for the second coursework?',
            'Is there a recommended reading for this topic?',
            'I am stuck on the last exercise — any hints?',
            'Are we allowed to work in pairs for this?',
        ];

        $post = Post::create([
            'forum_id' => $course->forum->id,
            'user_id' => $student->id,
            'content' => $questions[array_rand($questions)],
        ]);

        // The lecturer answers about half the time.
        if (mt_rand(0, 1) === 1) {
            Carbon::setTestNow(now()->addHours(mt_rand(2, 48)));

            Reply::create([
                'post_id' => $post->id,
                'user_id' => $course->instructor_id,
                'content' => 'Good question — see the materials posted for this week, and come to the next tutorial if it is still unclear.',
            ]);
        }
    }

    /**
     * Sit a quiz, answering as well as the student's ability allows.
     *
     * The answers are marked by the real Strategy, so the score on the Grade is
     * computed the same way it would be for a live attempt.
     */
    private function seedQuizAttempt(
        User $student,
        Quiz $quiz,
        int $ability,
        Carbon $termStart,
        GradingStrategyResolver $resolver
    ): void {
        Carbon::setTestNow($termStart->copy()->addDays(mt_rand(20, 85))->addHours(mt_rand(9, 21)));

        $questions = $quiz->questions;
        $target = max(0, min(100, $ability + mt_rand(-12, 12)));
        $shouldGetRight = (int) round(($target / 100) * $questions->count());

        $attempt = QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'student_id' => $student->id,
            'duration_seconds' => mt_rand(90, $quiz->time_limit * 60),
        ]);

        $earned = 0.0;
        $answeredRight = 0;

        foreach ($questions as $question) {
            $aimForCorrect = $answeredRight < $shouldGetRight;
            $response = $this->responseFor($question, $aimForCorrect);

            $result = $resolver->for($question)->grade($question, $response);

            $attempt->answers()->create([
                'question_id' => $question->id,
                'response' => $response,
                'is_correct' => $result->isCorrect,
                'awarded_score' => $result->score,
            ]);

            $earned += $result->score;

            if ($result->isCorrect) {
                $answeredRight++;
            }
        }

        // Writing the Grade is what triggers the CredentialAuthority.
        Grade::create([
            'quiz_attempt_id' => $attempt->id,
            'calculated_score' => round(($earned / max(1, $questions->count())) * 100, 2),
        ]);
    }

    /**
     * What this student types for a question.
     */
    private function responseFor(Question $question, bool $aimForCorrect): ?string
    {
        if ($question->type === Question::TYPE_MULTI) {
            $correct = $question->answers->where('is_correct', true);
            $wrong = $question->answers->where('is_correct', false);

            if ($aimForCorrect || $wrong->isEmpty()) {
                return $correct->pluck('id')->implode(',');
            }

            // A near miss: one correct option swapped for a wrong one, which
            // still satisfies the required count and earns partial credit.
            return $correct->take(max(0, $correct->count() - 1))
                ->pluck('id')
                ->push($wrong->random()->id)
                ->implode(',');
        }

        if ($question->type === Question::TYPE_MCQ) {
            $answer = $aimForCorrect
                ? $question->answers->firstWhere('is_correct', true)
                : $question->answers->where('is_correct', false)->random();

            return (string) ($answer?->id ?? '');
        }

        if (! $aimForCorrect) {
            return ['not sure', 'I think it is the other one', ''][array_rand([0, 1, 2])];
        }

        $correct = $question->answers->firstWhere('is_correct', true)?->answer_text ?? '';

        // A fifth of correct answers arrive with a typo, which the text
        // strategy is supposed to forgive.
        return mt_rand(1, 5) === 1 ? $this->typo($correct) : $correct;
    }

    /**
     * Swap two adjacent characters, the commonest real typing slip.
     */
    private function typo(string $text): string
    {
        if (strlen($text) < 4) {
            return $text;
        }

        $at = mt_rand(1, strlen($text) - 2);
        $swapped = $text;
        $swapped[$at] = $text[$at + 1];
        $swapped[$at + 1] = $text[$at];

        return $swapped;
    }

    /**
     * Upload, sometimes submit, sometimes get marked -- through the State
     * pattern, so no submission reaches a state it could not legally reach.
     */
    private function seedSubmission(User $student, Assignment $assignment, int $ability): void
    {
        // Stronger students hand in earlier; weaker ones drift past the deadline.
        $onTime = mt_rand(1, 100) <= min(92, $ability + 25);
        $when = $onTime
            ? $assignment->due_date->copy()->subDays(mt_rand(0, 9))->addHours(mt_rand(0, 20))
            : $assignment->due_date->copy()->addDays(mt_rand(1, 6))->addHours(mt_rand(0, 20));

        /*
         * An assignment whose deadline has not arrived yet can only have been
         * submitted early, never late. Without this the seeder happily dated
         * work "handed in" and "marked" weeks into the future.
         */
        if ($when->greaterThan($this->realNow)) {
            $when = $this->realNow->copy()->subDays(mt_rand(0, 12))->subHours(mt_rand(0, 20));
        }

        Carbon::setTestNow($when);

        $submission = Submission::create([
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'state' => 'draft',
            'file_path' => 'submissions/'.$assignment->id.'/demo-'.$student->id.'.pdf',
        ]);

        // One in eight never presses submit -- the exact case the "Due soon"
        // panel exists to catch.
        if (mt_rand(1, 8) === 1) {
            return;
        }

        $submission->state()->submit($submission);
        $submission->refresh();

        // Most, but not all, have been marked by now.
        if (mt_rand(1, 100) > 82) {
            return;
        }

        $marked = $when->copy()->addDays(mt_rand(2, 16));

        // Recent work simply has not been marked yet -- which is exactly what
        // fills the instructor's review queue.
        if ($marked->greaterThan($this->realNow)) {
            return;
        }

        Carbon::setTestNow($marked);

        $submission->state()->assignGrade(
            $submission,
            (float) max(0, min(100, $ability + mt_rand(-15, 10)))
        );
    }

    private function report(): void
    {
        $this->command->info('');
        $this->command->info('  students          '.User::where('name', 'like', 'student%')->count());
        $this->command->info('  enrolments        '.\DB::table('course_student')->count());
        $this->command->info('  quiz attempts     '.QuizAttempt::count());
        $this->command->info('  submissions       '.Submission::count());
        $this->command->info('  grades            '.Grade::count());
        $this->command->info('  forum posts       '.Post::count().' posts, '.Reply::count().' replies');
        $this->command->info('  progress records  '.\App\Models\StudentProgress::count());
        $this->command->info('  certificates      '.\App\Models\Certificate::count());
        $this->command->info('  badges awarded    '.\DB::table('badge_student')->count());
    }
}
