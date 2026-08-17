<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * MODULE 2 -- students joining and leaving courses.
 *
 * Section 7 gives enrolment to students only: an instructor "cannot enroll in a
 * course as a student", which the course.enroll permission key expresses.
 *
 * There are exactly two ways into a course and no third:
 *
 *   1. the instructor invites the student, who then accepts (store)
 *   2. the student types the course's class code (join)
 *
 * There is deliberately no browsable catalogue of courses to enrol in. A
 * student who has neither an invitation nor a code sees nothing to click,
 * because enrolment is the instructor's decision rather than the student's.
 */
class EnrolmentController extends Controller
{
    /**
     * The join-by-class-code form, reached from the + in the top bar.
     */
    public function create(Request $request): View
    {
        abort_unless($request->user()->can('course.enroll'), 403);

        return view('courses.join');
    }

    /**
     * Join a course by typing its class code.
     */
    public function join(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('course.enroll'), 403);

        $data = $request->validate([
            'class_code' => ['required', 'string', 'max:8'],
        ], [], ['class_code' => 'class code']);

        /*
         * Looked up case-insensitively: the code is read off a slide or a chat
         * message, and rejecting a correct code because of its capitalisation
         * would be a puzzle rather than a safeguard. Collisions are impossible
         * anyway -- the generator's alphabet has no character that folds onto
         * another under lower().
         */
        $course = Course::whereRaw('lower(class_code) = ?', [strtolower(trim($data['class_code']))])->first();

        if (! $course) {
            return back()->withInput()
                ->with('error', 'That class code does not match any course. Check it with your lecturer.');
        }

        if ($course->hasStudent($request->user())) {
            return redirect()->route('courses.show', $course)
                ->with('error', 'You are already enrolled in this course.');
        }

        $course->students()->attach($request->user()->id);

        // A standing invitation is now spent, so it stops appearing as a
        // pending one on either side.
        $course->invitations()
            ->where('student_id', $request->user()->id)
            ->whereNull('accepted_at')
            ->update(['accepted_at' => now()]);

        return redirect()->route('courses.show', $course)
            ->with('success', "Joined \"{$course->title}\".");
    }

    /**
     * Accept an invitation the instructor issued.
     */
    public function store(Request $request, Course $course): RedirectResponse
    {
        abort_unless($request->user()->can('course.enroll'), 403);

        if ($course->hasStudent($request->user())) {
            return back()->with('error', 'You are already enrolled in this course.');
        }

        /*
         * The invitation is the authorisation, so its absence is a 403 rather
         * than a validation message: without one this student has no business
         * knowing the course exists.
         */
        $invitation = CourseInvitation::where('course_id', $course->id)
            ->where('student_id', $request->user()->id)
            ->whereNull('accepted_at')
            ->first();

        abort_unless($invitation, 403, 'You have not been invited to this course.');

        $course->students()->attach($request->user()->id);
        $invitation->update(['accepted_at' => now()]);

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

        /*
         * The spent invitation goes with them. Leaving it accepted would let a
         * student who changed their mind rejoin with no instructor involved,
         * which is the loophole this whole flow exists to close.
         */
        $course->invitations()->where('student_id', $request->user()->id)->delete();

        return redirect()->route('courses.index')->with('success', "Left \"{$course->title}\".");
    }
}
