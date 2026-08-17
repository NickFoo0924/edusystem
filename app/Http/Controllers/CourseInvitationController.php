<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseInvitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * MODULE 2 -- the instructor's side of enrolment.
 *
 * Inviting is gated the way every other write to a course is: the permission
 * key first, then ownership, so one lecturer can never reach into another
 * lecturer's roster.
 */
class CourseInvitationController extends Controller
{
    /**
     * Invite one student, by the email address their account uses.
     */
    public function store(Request $request, Course $course): RedirectResponse
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
    public function destroy(Request $request, Course $course, CourseInvitation $invitation): RedirectResponse
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
    public function rotate(Request $request, Course $course): RedirectResponse
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
