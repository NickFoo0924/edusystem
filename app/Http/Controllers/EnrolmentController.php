<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Models\User;
use Illuminate\Validation\Rule;

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
         * Matched case-insensitively: the code is read off a slide or a chat
         * message, and rejecting a correct one over its capitalisation would be
         * a puzzle rather than a safeguard. The column collation
         * (utf8mb4_unicode_ci) does that comparison, so this stays a plain
         * Eloquent where() -- Section 5 forbids raw SQL, and an earlier
         * whereRaw('lower(...)') here was in breach of it.
         */
        $course = Course::where('class_code', trim($data['class_code']))->first();

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

    /*
     * The instructor's side of enrolment.
     *
     * Gated the way every other write to a course is: the permission key
     * first, then ownership, so one lecturer cannot reach into another
     * lecturer's roster.
     */
    /**
     * Invite one student, by the email address their account uses.
     */
    public function invite(Request $request, Course $course): RedirectResponse
    {
        $this->authoriseOwner($request, $course);

        $data = $request->validate([
            'email' => ['required', 'email', Rule::exists('users', 'email')],
        ], [
            'email.exists' => 'No account uses that email address. An administrator invites people into the system first.',
        ]);

        $student = User::where('email', $data['email'])->first();

        /*
         * Checked as a permission rather than a role, so this follows the
         * matrix: whoever may enrol is who may be invited. It also blocks the
         * two nonsense cases -- inviting a lecturer, or inviting the admin.
         */
        if (! $student->can('course.enroll')) {
            return back()->with('error', "{$student->name} is not a student account and cannot be enrolled.");
        }

        if ($course->hasStudent($student)) {
            return back()->with('error', "{$student->name} is already enrolled in this course.");
        }

        $existing = CourseInvitation::where('course_id', $course->id)
            ->where('student_id', $student->id)
            ->first();

        if ($existing) {
            return back()->with('error', "{$student->name} has already been invited.");
        }

        CourseInvitation::create([
            'course_id' => $course->id,
            'student_id' => $student->id,
            'invited_by' => $request->user()->id,
        ]);

        return back()->with('success', "Invited {$student->name}. It appears on their Courses page to accept.");
    }

    /**
     * Withdraw an invitation that has not been accepted.
     */
    public function withdrawInvitation(Request $request, Course $course, CourseInvitation $invitation): RedirectResponse
    {
        $this->authoriseOwner($request, $course);

        abort_unless($invitation->course_id === $course->id, 404);

        // An accepted invitation is a record of how someone got in, not a
        // pending action, so withdrawing it would rewrite history without
        // removing the enrolment it produced.
        abort_unless($invitation->isPending(), 403, 'That invitation has already been accepted.');

        $name = $invitation->student->name;
        $invitation->delete();

        return back()->with('success', "Withdrew the invitation to {$name}.");
    }

    /**
     * Issue a new class code, invalidating the old one.
     */
    public function rotateClassCode(Request $request, Course $course): RedirectResponse
    {
        $this->authoriseOwner($request, $course);

        $course->update(['class_code' => Course::generateClassCode()]);

        return back()->with('success', 'New class code issued. The previous one no longer works.');
    }

    /**
     * The same two checks the rest of Module 2 applies to course writes.
     */
    private function authoriseOwner(Request $request, Course $course): void
    {
        abort_unless($request->user()->can('course.create'), 403);
        abort_unless($course->instructor_id === $request->user()->id, 403, 'This course belongs to another instructor.');
    }
}
