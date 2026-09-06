<?php

/**
 * LearnSync -- REST web service controller
 *
 * Module 2: Academic Resources Repository
 *
 * @author Foo Chong Xian
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Support\Ifa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * MODULE 2 (Foo Chong Xian) exposes: getCourseInfo.
 *
 * Consumed by Module 1, which needs the course code and title to print on a
 * certificate, and by Module 4, which needs them to label a quiz.
 *
 * This is the boundary in EduSystem.md Section 2A made real over HTTP. Module 2
 * is the sole writer of `courses`, so the other modules ask this service rather
 * than querying Module 2's tables directly.
 */
class CourseApiController extends Controller
{
    public function info(Request $request): JsonResponse
    {
        $validator = validator($request->all(), Ifa::baseRules() + [
            'courseId' => ['required', 'integer', 'min:1'],
            'queryFlag' => ['required', 'integer', 'in:1,2'],
        ]);

        if ($validator->fails()) {
            return Ifa::fail($request, ['errors' => $validator->errors()->all()]);
        }

        try {
            $course = Course::with('instructor')->find($request->integer('courseId'));

            if ($course === null) {
                return Ifa::fail($request, [
                    'message' => 'No course exists with that ID.',
                ], 404);
            }

            $data = [
                'courseCode' => $course->code,
                'courseTitle' => $course->title,
                'studentCount' => $course->students()->count(),
            ];

            // queryFlag 2 additionally names the lecturer. The class code is
            // never returned by this service at any flag: holding it grants
            // enrolment, so it must not travel over an integration channel.
            if ($request->integer('queryFlag') === 2) {
                $data['instructorName'] = $course->instructor?->name;
                $data['instructorEmail'] = $course->instructor?->contactEmail();
            }

            return Ifa::success($request, $data);
        } catch (Throwable $e) {
            Log::error('getCourseInfo failed', ['error' => $e->getMessage()]);

            return Ifa::error($request);
        }
    }
}
