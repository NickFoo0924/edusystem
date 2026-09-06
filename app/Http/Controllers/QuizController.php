<?php

/**
 * LearnSync -- HTTP controller
 *
 * Module 4: Skill Assessment & Quiz
 *
 * @author Wong Siew Lam
 */

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Quiz;
use App\Patterns\Strategy\GradingStrategyResolver;
use App\Support\Api\CourseInfoClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Models\Question;

/**
 * MODULE 4 (Wong Siew Lam) -- instructors building quizzes.
 *
 * Section 7: instructors create quizzes; students take them; administrators do
 * neither.
 */
class QuizController extends Controller
{
    public function __construct(
        private GradingStrategyResolver $resolver,
        private CourseInfoClient $courses,
    ) {
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
            /*
             * MODULE 4 CONSUMES MODULE 2's WEB SERVICE.
             *
             * The course a quiz belongs to is labelled from Module 2's
             * getCourseInfo service rather than by reading Module 2's tables.
             * Module 2 is the sole owner of course data (EduSystem.md Section
             * 2A), so the boundary holds even for a read as small as a title.
             *
             * Null when Module 2 is unreachable, and the view falls back to
             * the course already loaded locally. A quiz must stay usable when
             * another module is down.
             */
            'courseInfo' => $this->courses->fetchWithInstructor($quiz->course_id),
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

    /*
     * Questions. A question only exists inside a quiz, so it has no resource
     * of its own -- the quiz owns it, and the same ownership check guards
     * both.
     */
    public function storeQuestion(Request $request, Quiz $quiz): RedirectResponse
    {
        $this->authoriseOwner($request, $quiz);

        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', array_keys(GradingStrategyResolver::availableTypes()))],
            'question_text' => ['required', 'string', 'max:2000'],
            'options' => ['required_if:type,mcq,multi', 'array'],
            'options.*' => ['nullable', 'string', 'max:255'],
            'correct_option' => ['required_if:type,mcq', 'nullable', 'integer'],
            'correct_options' => ['required_if:type,multi', 'array'],
            'correct_options.*' => ['integer'],
            'accepted_answers' => ['required_if:type,text', 'nullable', 'string', 'max:1000'],
        ], [
            'correct_option.required_if' => 'Mark which option is the correct one.',
            'correct_options.required_if' => 'Tick which options are correct.',
            'accepted_answers.required_if' => 'Give at least one accepted answer.',
        ]);

        if ($data['type'] === Question::TYPE_MULTI) {
            /*
             * A multiple-answer question needs at least two correct options.
             * With one it is simply a single-choice question wearing the wrong
             * hat, and the "select exactly N" instruction would read oddly.
             * Checked against options that were actually filled in, so ticking
             * a blank row cannot inflate the count.
             */
            $filled = array_keys(array_filter(
                array_values($data['options']),
                fn ($text) => filled($text)
            ));
            $correct = array_intersect($data['correct_options'], $filled);

            if (count($correct) < Question::MIN_MULTI_ANSWERS) {
                return back()->withInput()->with('error',
                    'A multiple-answer question needs at least '.Question::MIN_MULTI_ANSWERS
                    .' correct options. Tick more, or use "one answer" instead.');
            }
        }

        $question = $quiz->questions()->create([
            'type' => $data['type'],
            'question_text' => $data['question_text'],
        ]);

        match ($data['type']) {
            Question::TYPE_MCQ => $this->storeChoices($question, $data['options'], [(int) $data['correct_option']]),
            Question::TYPE_MULTI => $this->storeChoices($question, $data['options'], $data['correct_options']),
            default => $this->storeAcceptedAnswers($question, $data['accepted_answers']),
        };

        return back()->with('success', 'Question added.');
    }

    public function destroyQuestion(Request $request, Question $question): RedirectResponse
    {
        $this->authoriseOwner($request, $question->quiz);

        $quiz = $question->quiz;
        $question->delete();

        return redirect()->route('quizzes.show', $quiz)->with('success', 'Question removed.');
    }

    /**
     * Store the options, flagging the chosen indexes correct.
     *
     * Takes a list of correct indexes rather than one, so single-choice and
     * multiple-answer questions share the same path -- single choice is just
     * the case where the list holds one entry.
     *
     * @param  array<int, string|null>  $options
     * @param  array<int, int>  $correctIndexes
     */
    private function storeChoices(Question $question, array $options, array $correctIndexes): void
    {
        $correctIndexes = array_map('intval', $correctIndexes);

        foreach (array_values($options) as $index => $text) {
            if (blank($text)) {
                continue;
            }

            $question->answers()->create([
                'answer_text' => $text,
                'is_correct' => in_array($index, $correctIndexes, true),
            ]);
        }
    }

}
