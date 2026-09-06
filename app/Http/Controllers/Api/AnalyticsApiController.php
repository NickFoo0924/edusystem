<?php

/**
 * LearnSync -- REST web service controller
 *
 * Module 5: Academic Progress Analytics
 *
 * @author Ong Kwong Wei
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Grade;
use App\Models\QuizAttempt;
use App\Models\Submission;
use App\Support\GradeScale;
use App\Support\Ifa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * MODULE 5 (Ong Kwong Wei) exposes: getCourseAnalytics.
 *
 * Consumed by Module 2, which shows a class performance summary on the course
 * page without having to compute marks itself.
 *
 * Returns figures about a whole cohort and never about a named individual.
 * That is deliberate: a service that returned per-student marks would let any
 * key holder assemble a full transcript for somebody else, which is a data
 * protection problem the summary shape avoids entirely.
 */
class AnalyticsApiController extends Controller
{
    public function courseAnalytics(Request $request): JsonResponse
    {
        $validator = validator($request->all(), Ifa::baseRules() + [
            'courseId' => ['required', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return Ifa::fail($request, ['errors' => $validator->errors()->all()]);
        }

        try {
            $course = Course::find($request->integer('courseId'));

            if ($course === null) {
                return Ifa::fail($request, [
                    'message' => 'No course exists with that ID.',
                ], 404);
            }

            $scores = $this->scoresFor($course);

            if ($scores->isEmpty()) {
                return Ifa::success($request, [
                    'courseCode' => $course->code,
                    'gradedCount' => 0,
                    'message' => 'No work has been marked in this course yet.',
                ]);
            }

            $average = round((float) $scores->avg(), 2);

            return Ifa::success($request, [
                'courseCode' => $course->code,
                'gradedCount' => $scores->count(),
                'averageScore' => $average,
                'averageGrade' => GradeScale::letterFor($average),
                'highestScore' => round((float) $scores->max(), 2),
                'lowestScore' => round((float) $scores->min(), 2),
                'passCount' => $scores->filter(fn ($s) => GradeScale::isPass($s))->count(),
                'distribution' => $this->distribution($scores),
            ]);
        } catch (Throwable $e) {
            Log::error('getCourseAnalytics failed', ['error' => $e->getMessage()]);

            return Ifa::error($request);
        }
    }

    /**
     * Every mark earned in a course, from quizzes and coursework alike.
     */
    private function scoresFor(Course $course)
    {
        $quizScores = Grade::whereIn(
            'quiz_attempt_id',
            QuizAttempt::whereIn('quiz_id', $course->quizzes()->select('id'))->select('id')
        )->pluck('calculated_score');

        $submissionScores = Grade::whereIn(
            'submission_id',
            Submission::whereIn('assignment_id', $course->assignments()->select('id'))->select('id')
        )->pluck('calculated_score');

        return $quizScores->merge($submissionScores);
    }

    /**
     * How many marks fell into each letter family, A through F.
     *
     * @return array<string, int>
     */
    private function distribution($scores): array
    {
        $counts = [];

        foreach (GradeScale::families() as $family) {
            $counts[$family] = $scores
                ->filter(fn ($score) => GradeScale::familyFor($score) === $family)
                ->count();
        }

        return $counts;
    }
}
