<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Grade;
use App\Models\QuizAttempt;
use App\Support\GradeScale;
use App\Support\Ifa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * MODULE 4 (Wong Siew Lam) exposes: getQuizResult.
 *
 * Consumed by Module 3, which needs a student's mark in order to tell them
 * their quiz has been graded, and by Module 5's cohort analytics.
 *
 * Returns the student's best attempt at a quiz. Best rather than latest,
 * because a re-sit that went badly should not overwrite a good earlier result
 * when another module asks "how did this student do".
 */
class QuizApiController extends Controller
{
    public function result(Request $request): JsonResponse
    {
        $validator = validator($request->all(), Ifa::baseRules() + [
            'quizId' => ['required', 'integer', 'min:1'],
            'studentId' => ['required', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return Ifa::fail($request, [
                'attempted' => false,
                'errors' => $validator->errors()->all(),
            ]);
        }

        try {
            $attemptIds = QuizAttempt::where('quiz_id', $request->integer('quizId'))
                ->where('student_id', $request->integer('studentId'))
                ->pluck('id');

            if ($attemptIds->isEmpty()) {
                // Not an error. The student simply has not sat this quiz.
                return Ifa::success($request, [
                    'attempted' => false,
                    'attemptCount' => 0,
                ]);
            }

            $grades = Grade::whereIn('quiz_attempt_id', $attemptIds)->get();

            if ($grades->isEmpty()) {
                return Ifa::success($request, [
                    'attempted' => true,
                    'attemptCount' => $attemptIds->count(),
                    'graded' => false,
                ]);
            }

            $best = (float) $grades->max('calculated_score');

            return Ifa::success($request, [
                'attempted' => true,
                'graded' => true,
                'attemptCount' => $attemptIds->count(),
                'bestScore' => round($best, 2),
                'letterGrade' => GradeScale::letterFor($best),
                'passed' => GradeScale::isPass($best),
            ]);
        } catch (Throwable $e) {
            Log::error('getQuizResult failed', ['error' => $e->getMessage()]);

            return Ifa::error($request, ['attempted' => false]);
        }
    }
}
