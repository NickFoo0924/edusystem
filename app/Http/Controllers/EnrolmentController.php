<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Course;
use App\Models\CourseInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Models\User;
use Illuminate\Validation\Rule;

/**
 * MODULE 2 -- students joining courses, and lecturers removing them.
 *
 * Section 7 gives enrolment to students only: an instructor "cannot enroll in a
 * course as a student", which the course.enroll permission key expresses.
 *
 * There are exactly two ways into a course and no third:
 *
 *   1. the instructor invites the student, who then accepts (store)
 *   2. the student types the course's class code (join)
 *
 * There is exactly one way out, and it is not the student's to take:
 *
 *   3. the lecturer who owns the course removes them (removeStudent)
 *
 * The asymmetry is deliberate. Joining is something a student may be trusted
 * with because both routes in already required the lecturer's consent -- an
 * invitation they issued, or a class code they handed out. Leaving is not the
 * same act in reverse: it would let a student drop a class to escape an
 * assessment, and take their submissions and grades out of the lecturer's view
 * with them. So destroy() refuses, and removal is the owning lecturer's alone.
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

    /**
     * Refused, always: a student may not remove themselves from a course.
     *
     * Enrolment is the instructor's decision in both directions. A student who
     * has joined is in the class until the lecturer who owns it says otherwise
     * -- leaving unaided would let somebody walk away from an assessment, and
     * take the submissions, attempts and grades attached to their enrolment
     * out of the lecturer's view with them.
     *
     * The route is deliberately kept rather than deleted so that a direct call
     * is answered with an explicit 403 explaining the rule, instead of a 404
     * that reads like the feature is merely missing. Hiding the button is not
     * the control; this is.
     *
     * Removal now lives in removeStudent(), which only the owning lecturer can
     * reach.
     */
    public function destroy(Request $request, Course $course): RedirectResponse
    {
        abort(403, 'Students cannot leave a course themselves. Ask the lecturer who teaches it to remove you.');
    }

    /**
     * The lecturer removes a student from their own course.
     *
     * Guarded exactly as every other roster write in this controller is
     * (authoriseOwner): the course.create permission key, which only the
     * instructor role holds, and then ownership of this particular course --
     * so one lecturer cannot empty another lecturer's class.
     */
    public function removeStudent(Request $request, Course $course, User $student): RedirectResponse
    {
        $this->authoriseOwner($request, $course);

        // Not enrolled is a 404 rather than a silent success: the caller asked
        // to undo something that was never true.
        abort_unless($course->hasStudent($student), 404, 'That student is not enrolled in this course.');

        /*
         * The same safeguard the old self-service leave carried, now applied to
         * the target rather than the actor. Removing the enrolment behind an
         * issued credential would orphan the work the certificate attests to,
         * and the public verification page would still be serving it.
         */
        if ($course->certificates()->where('student_id', $student->id)->whereNull('revoked_at')->exists()) {
            return back()->with(
                'error',
                "{$student->name} holds a certificate for this course, so the enrolment cannot be removed. Revoke the credential first."
            );
        }

        $course->students()->detach($student->id);

        /*
         * The spent invitation goes with them, so a removed student does not
         * still have a standing invitation to accept.
         */
        $course->invitations()->where('student_id', $student->id)->delete();

        // Removing somebody from a class is a decision worth being able to
        // point at later, so it joins the audit trail.
        ActivityLog::record('course.student_removed', $student);

        return back()->with('success', "Removed {$student->name} from \"{$course->title}\".");
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
