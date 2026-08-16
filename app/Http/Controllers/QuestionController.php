<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\Quiz;
use App\Patterns\Strategy\GradingStrategyResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * MODULE 4 -- questions and their answers.
 *
 * An MCQ carries several options with one flagged correct. A fill-in-the-blank
 * carries one or more accepted wordings, all flagged correct, which
 * TextMatchGradingStrategy compares against.
 */
class QuestionController extends Controller
{
    public function store(Request $request, Quiz $quiz): RedirectResponse
    {
        $this->authoriseOwner($request, $quiz);

        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', array_keys(GradingStrategyResolver::availableTypes()))],
            'question_text' => ['required', 'string', 'max:2000'],
            'options' => ['required_if:type,mcq', 'array'],
            'options.*' => ['nullable', 'string', 'max:255'],
            'correct_option' => ['required_if:type,mcq', 'nullable', 'integer'],
            'accepted_answers' => ['required_if:type,text', 'nullable', 'string', 'max:1000'],
        ], [
            'correct_option.required_if' => 'Mark which option is the correct one.',
            'accepted_answers.required_if' => 'Give at least one accepted answer.',
        ]);

        $question = $quiz->questions()->create([
            'type' => $data['type'],
            'question_text' => $data['question_text'],
        ]);

        if ($data['type'] === Question::TYPE_MCQ) {
            $this->storeChoices($question, $data['options'], (int) $data['correct_option']);
        } else {
            $this->storeAcceptedAnswers($question, $data['accepted_answers']);
        }

        return back()->with('success', 'Question added.');
    }

    public function destroy(Request $request, Question $question): RedirectResponse
    {
        $this->authoriseOwner($request, $question->quiz);

        $quiz = $question->quiz;
        $question->delete();

        return redirect()->route('quizzes.show', $quiz)->with('success', 'Question removed.');
    }

    /**
     * @param  array<int, string|null>  $options
     */
    private function storeChoices(Question $question, array $options, int $correctIndex): void
    {
        foreach (array_values($options) as $index => $text) {
            if (blank($text)) {
                continue;
            }

            $question->answers()->create([
                'answer_text' => $text,
                'is_correct' => $index === $correctIndex,
            ]);
        }
    }

    /**
     * One accepted wording per line; every one counts as correct.
     */
    private function storeAcceptedAnswers(Question $question, string $raw): void
    {
        foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
            if (blank(trim($line))) {
                continue;
            }

            $question->answers()->create([
                'answer_text' => trim($line),
                'is_correct' => true,
            ]);
        }
    }

    private function authoriseOwner(Request $request, Quiz $quiz): void
    {
        abort_unless($request->user()->can('quiz.create'), 403);
        abort_unless($quiz->course->instructor_id === $request->user()->id, 403);
    }
}
