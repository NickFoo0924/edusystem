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

    public function destroy(Request $request, Question $question): RedirectResponse
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
