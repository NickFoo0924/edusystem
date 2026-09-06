<?php

/**
 * LearnSync -- HTTP controller
 *
 * Module 5: Academic Progress Analytics
 *
 * @author Ong Kwong Wei
 */

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Submission;
use App\Patterns\State\IllegalSubmissionTransition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * MODULE 5 -- the State pattern in use.
 *
 * Not one method here asks "what state is this in?". Each asks the submission's
 * state object to do the thing, and the state either does it or refuses. That
 * is the whole point: the rules about what is allowed live in
 * app/Patterns/State, not scattered through this controller.
 */
class SubmissionController extends Controller
{
    /**
     * Upload or replace the file. Allowed only while the work is a draft.
     */
    public function store(Request $request, Assignment $assignment): RedirectResponse
    {
        $this->authoriseStudent($request, $assignment);

        // The instructor's late policy is checked before the State pattern is
        // consulted. The two rules are on different axes: the state knows
        // whether *this submission* may still be edited, the assignment knows
        // whether it is still taking work at all.
        if ($assignment->isClosed()) {
            return back()->with('error', 'This assignment closed at its deadline and is no longer accepting work.');
        }

        $request->validate([
            'file' => ['required', 'file', 'max:20480'],
        ]);

        $submission = Submission::firstOrCreate(
            ['assignment_id' => $assignment->id, 'student_id' => $request->user()->id],
            ['state' => 'draft']
        );

        $path = $request->file('file')->store('submissions/'.$assignment->id, 'local');

        try {
            $submission->state()->updateFile($submission, $path);
        } catch (IllegalSubmissionTransition $e) {
            // Do not leave the orphaned upload behind after a refusal.
            Storage::disk('local')->delete($path);

            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'File saved as a draft. Submit it when you are ready.');
    }

    /**
     * Hand the work in. The state moves draft -> submitted and locks edits.
     */
    public function submit(Request $request, Submission $submission): RedirectResponse
    {
        abort_unless($submission->student_id === $request->user()->id, 403);
        abort_unless($request->user()->can('assignment.submit'), 403);

        if (blank($submission->file_path)) {
            return back()->with('error', 'Upload a file before submitting.');
        }

        $assignment = $submission->assignment;

        if ($assignment->isClosed()) {
            return back()->with('error', 'This assignment closed at its deadline. Your draft can no longer be submitted.');
        }

        // Captured before the transition, because submit() stamps submitted_at
        // and the answer would change underneath us.
        $isLate = $assignment->wouldBeLate();

        try {
            $submission->state()->submit($submission);
        } catch (IllegalSubmissionTransition $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $isLate
            ? 'Submitted, and recorded as turned in late. Your instructor will review it.'
            : 'Submitted on time. Your instructor will review it.');
    }

    /**
     * Instructor marks the work. The state moves submitted -> graded and writes
     * the authoritative Grade, which triggers the CredentialAuthority.
     */
    public function grade(Request $request, Submission $submission): RedirectResponse
    {
        $assignment = $submission->assignment;

        abort_unless($request->user()->can('grade.assign'), 403);
        abort_unless($assignment->course->instructor_id === $request->user()->id, 403);

        $data = $request->validate([
            'calculated_score' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        try {
            $submission->state()->assignGrade($submission, (float) $data['calculated_score']);
        } catch (IllegalSubmissionTransition $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Grade recorded.');
    }

    /**
     * Download a submitted file — the student's own, or the instructor's to mark.
     */
    public function download(Request $request, Submission $submission): StreamedResponse
    {
        $isOwner = $submission->student_id === $request->user()->id;
        $isInstructor = $submission->assignment->course->instructor_id === $request->user()->id;

        abort_unless($isOwner || $isInstructor, 403);
        abort_if(blank($submission->file_path), 404);
        abort_unless(Storage::disk('local')->exists($submission->file_path), 404);

        return Storage::disk('local')->download(
            $submission->file_path,
            $submission->student->name.' — '.$submission->assignment->title.'.'
                .pathinfo($submission->file_path, PATHINFO_EXTENSION)
        );
    }

    private function authoriseStudent(Request $request, Assignment $assignment): void
    {
        abort_unless($request->user()->can('assignment.submit'), 403, 'Only students submit work.');
        abort_unless($assignment->course->hasStudent($request->user()), 403, 'You are not enrolled in this course.');
    }
}
