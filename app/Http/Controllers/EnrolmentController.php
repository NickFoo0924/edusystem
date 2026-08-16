<?php

namespace App\Http\Controllers;

use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * MODULE 2 -- students joining and leaving courses.
 *
 * Section 7 gives enrolment to students only: an instructor "cannot enroll in a
 * course as a student", which the course.enroll permission key expresses.
 */
class EnrolmentController extends Controller
{
    public function store(Request $request, Course $course): RedirectResponse
    {
        abort_unless($request->user()->can('course.enroll'), 403);

        if ($course->hasStudent($request->user())) {
            return back()->with('error', 'You are already enrolled in this course.');
        }

        $course->students()->attach($request->user()->id);

        return redirect()->route('courses.show', $course)
            ->with('success', "Enrolled in \"{$course->title}\".");
    }

    public function destroy(Request $request, Course $course): RedirectResponse
    {
        abort_unless($request->user()->can('course.enroll'), 403);

        // Leaving would orphan the work behind an issued credential, so it is
        // refused once anything has been earned here.
        if ($course->certificates()->where('student_id', $request->user()->id)->exists()) {
            return back()->with('error', 'You hold a certificate for this course and cannot unenrol.');
        }

        $course->students()->detach($request->user()->id);

        return redirect()->route('courses.index')->with('success', "Left \"{$course->title}\".");
    }
}
