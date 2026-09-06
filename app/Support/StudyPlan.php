<?php

/**
 * LearnSync -- Support helper
 *
 * Module 2: Academic Resources Repository
 *
 * @author Foo Chong Xian
 */

namespace App\Support;

use App\Models\Course;
use App\Models\QuizAttempt;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The suggested order to work through a course.
 *
 * A course page lists everything it holds at once, which answers "what is in
 * this course" but not "what should I do next". This turns the same content
 * into a sequence: read, practise, then be assessed.
 *
 * Not to be confused with a LearningPath, which is Module 1's ordered
 * collection of whole courses. This is a plan inside one course.
 *
 * Only the assessment steps can report completion. There is no view-tracking
 * table -- `materials_viewed` is always 0 -- so a reading step is never marked
 * done, because claiming somebody read something the system cannot observe
 * would be a lie on a progress indicator. Reading steps are shown as open, and
 * the count at the top counts only what is genuinely verifiable.
 */
class StudyPlan
{
    /**
     * Reading before practice, practice before assessment. `other` is left out
     * of the plan: it is the catch-all heading, so it has no fixed place in a
     * teaching order.
     */
    private const READING_ORDER = ['lecture', 'tutorial', 'practical'];

    /**
     * @return Collection<int, array{
     *     title: string, detail: string, kind: string,
     *     done: bool, verifiable: bool, url: ?string
     * }>
     */
    public static function for(Course $course, User $student): Collection
    {
        $steps = collect();

        foreach (self::READING_ORDER as $type) {
            $items = $course->materials->where('type', $type);

            if ($items->isEmpty()) {
                continue;
            }

            $steps->push([
                'title' => self::readingTitle($type),
                'detail' => $items->count().' '.str($items->count() === 1 ? 'item' : 'items'),
                'kind' => 'Materials',
                'done' => false,
                'verifiable' => false,
                'url' => null,
            ]);
        }

        $attempted = QuizAttempt::where('student_id', $student->id)
            ->whereIn('quiz_id', $course->quizzes->pluck('id'))
            ->pluck('quiz_id')
            ->all();

        foreach ($course->quizzes as $quiz) {
            $steps->push([
                'title' => 'Attempt "'.$quiz->title.'"',
                'detail' => $quiz->questions->count().' questions · '.$quiz->time_limit.' min',
                'kind' => 'Quiz',
                'done' => in_array($quiz->id, $attempted, true),
                'verifiable' => true,
                'url' => route('quizzes.show', $quiz),
            ]);
        }

        $handedIn = Submission::where('student_id', $student->id)
            ->whereIn('assignment_id', $course->assignments->pluck('id'))
            ->whereNotNull('submitted_at')
            ->pluck('assignment_id')
            ->all();

        foreach ($course->assignments->sortBy('due_date') as $assignment) {
            $steps->push([
                'title' => 'Submit "'.$assignment->title.'"',
                'detail' => 'Due '.$assignment->due_date->format('j M, g:ia'),
                'kind' => 'Assignment',
                'done' => in_array($assignment->id, $handedIn, true),
                'verifiable' => true,
                'url' => route('assignments.show', $assignment),
            ]);
        }

        return $steps->values();
    }

    /**
     * How many of the steps that can be checked have been finished.
     *
     * @return array{done: int, total: int}
     */
    public static function progress(Collection $steps): array
    {
        $verifiable = $steps->where('verifiable', true);

        return [
            'done' => $verifiable->where('done', true)->count(),
            'total' => $verifiable->count(),
        ];
    }

    /**
     * The first step still outstanding -- what the plan is pointing at.
     */
    public static function nextIndex(Collection $steps): ?int
    {
        foreach ($steps as $i => $step) {
            if (! $step['done']) {
                return $i;
            }
        }

        return null;
    }

    private static function readingTitle(string $type): string
    {
        return match ($type) {
            'lecture' => 'Read the lecture notes',
            'tutorial' => 'Work through the tutorial questions',
            'practical' => 'Complete the practical questions',
            default => 'Review the materials',
        };
    }
}
