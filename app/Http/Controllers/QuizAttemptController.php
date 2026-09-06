<?php

/**
 * LearnSync -- HTTP controller
 *
 * Module 4: Skill Assessment & Quiz
 *
 * @author Wong Siew Lam
 */

namespace App\Http\Controllers;

use App\Models\Grade;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Patterns\Strategy\GradingStrategyResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * MODULE 4 / MODULE 5 boundary -- a student sitting a quiz.
 *
 * This is where the Strategy pattern earns its place. The loop below never asks
 * what type a question is: it hands each one to the resolver and calls grade().
 * MCQs and fill-in-the-blanks are marked by completely different algorithms and
 * this method cannot tell them apart.
 *
 * The Grade record it writes belongs to Module 5, which is the sole writer of
 * `grades` (EduSystem.md Section 2A). Writing it is what wakes the
 * CredentialAuthority in workflow Step 5.
 */
class QuizAttemptController extends Controller
{
    public function __construct(private GradingStrategyResolver $resolver)
    {
    }

    /**
     * The question paper.
     */
    public function create(Request $request, Quiz $quiz): View
    {
        $this->authoriseStudent($request, $quiz);

        $quiz->load('questions.answers');

        abort_if($quiz->questions->isEmpty(), 404, 'This quiz has no questions yet.');

        return view('quizzes.attempt', compact('quiz'));
    }

    /**
     * Mark the paper and record the attempt.
     */
    public function store(Request $request, Quiz $quiz): RedirectResponse
    {
        $this->authoriseStudent($request, $quiz);

        $quiz->load('questions.answers');

        $data = $request->validate([
            'responses' => ['array'],
            // A multiple-answer question posts an array of ids; the other two
            // post a single string.
            'responses.*' => ['nullable'],
            'responses.*.*' => ['nullable', 'string', 'max:2000'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
        ]);

        /*
         * Flatten to one string per question, so every strategy receives the
         * same shape and quiz_attempt_answers.response stays a single column.
         */
        $responses = collect($data['responses'] ?? [])
            ->map(fn ($value) => is_array($value)
                ? collect($value)->filter()->implode(',')
                : $value)
            ->all();

        $attempt = DB::transaction(function () use ($request, $quiz, $responses, $data) {
            $attempt = QuizAttempt::create([
                'quiz_id' => $quiz->id,
                'student_id' => $request->user()->id,
                'duration_seconds' => (int) ($data['duration_seconds'] ?? 0),
            ]);

            $earned = 0.0;

            foreach ($quiz->questions as $question) {
                // The strategy is chosen per question, at run time.
                $strategy = $this->resolver->for($question);
                $result = $strategy->grade($question, $responses[$question->id] ?? null);

                $attempt->answers()->create([
                    'question_id' => $question->id,
                    'response' => $responses[$question->id] ?? null,
                    'is_correct' => $result->isCorrect,
                    'awarded_score' => $result->score,
                ]);

                $earned += $result->score;
            }

            $percentage = round(($earned / max(1, $quiz->questions->count())) * 100, 2);

            // Module 5's authoritative record. Its creation triggers the
            // CredentialAuthority: progress, badges, then any certificate.
            Grade::create([
                'quiz_attempt_id' => $attempt->id,
                'calculated_score' => $percentage,
            ]);

            return $attempt;
        });

        return redirect()->route('attempts.show', $attempt)
            ->with('success', 'Quiz submitted and marked.');
    }

    /**
     * Review a finished attempt, question by question.
     */
    public function show(Request $request, QuizAttempt $attempt): View
    {
        $attempt->load(['quiz.course', 'answers.question.answers', 'grade', 'student']);

        $user = $request->user();
        $isOwner = $attempt->student_id === $user->id;
        $isInstructor = $attempt->quiz->course->instructor_id === $user->id;

        // Section 7: a student cannot view another student's results.
        abort_unless($isOwner || $isInstructor, 403);

        return view('quizzes.result', [
            'attempt' => $attempt,
            'isOwner' => $isOwner,
        ]);
    }

    private function authoriseStudent(Request $request, Quiz $quiz): void
    {
        abort_unless($request->user()->can('quiz.attempt'), 403, 'Only students may take quizzes.');
        abort_unless($quiz->course->hasStudent($request->user()), 403, 'You are not enrolled in this course.');
    }
}
