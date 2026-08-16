<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Quiz;
use App\Patterns\Strategy\GradingStrategyResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * MODULE 4 (Wong Siew Lam) -- instructors building quizzes.
 *
 * Section 7: instructors create quizzes; students take them; administrators do
 * neither.
 */
class QuizController extends Controller
{
    public function __construct(private GradingStrategyResolver $resolver)
    {
    }

    public function create(Request $request, Course $course): View
    {
        $this->authoriseOwner($request, $course);

        return view('quizzes.create', compact('course'));
    }

    public function store(Request $request, Course $course): RedirectResponse
    {
        $this->authoriseOwner($request, $course);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'time_limit' => ['required', 'integer', 'min:1', 'max:300'],
        ]);

        $quiz = $course->quizzes()->create($data);

        return redirect()->route('quizzes.show', $quiz)
            ->with('success', 'Quiz created. Add some questions.');
    }

    /**
     * The instructor's editor, or the student's start page.
     */
    public function show(Request $request, Quiz $quiz): View
    {
        $user = $request->user();
        $quiz->load(['course', 'questions.answers']);

        $isOwner = $quiz->course->instructor_id === $user->id;

        if (! $isOwner) {
            abort_unless($user->can('quiz.attempt'), 403);
            abort_unless($quiz->course->hasStudent($user), 403, 'You are not enrolled in this course.');
        }

        return view('quizzes.show', [
            'quiz' => $quiz,
            'isOwner' => $isOwner,
            'questionTypes' => GradingStrategyResolver::availableTypes(),
            // Each question can explain how it will be marked, straight from
            // the strategy that will mark it.
            'strategyNotes' => $quiz->questions->mapWithKeys(
                fn ($question) => [$question->id => $this->resolver->for($question)->describe()]
            ),
            'previousAttempts' => $isOwner
                ? collect()
                : $quiz->attempts()->with('grade')->where('student_id', $user->id)->latest()->get(),
        ]);
    }

    public function destroy(Request $request, Quiz $quiz): RedirectResponse
    {
        $this->authoriseOwner($request, $quiz->course);

        $course = $quiz->course;
        $quiz->delete();

        return redirect()->route('courses.show', $course)->with('success', 'Quiz deleted.');
    }

    private function authoriseOwner(Request $request, Course $course): void
    {
        abort_unless($request->user()->can('quiz.create'), 403);
        abort_unless($course->instructor_id === $request->user()->id, 403, 'This course belongs to another instructor.');
    }
}
