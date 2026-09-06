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
use App\Models\Course;
use App\Models\Submission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * MODULE 5 (Ong Kwong Wei) -- assignments and the review queue.
 */
class AssignmentController extends Controller
{
    public function create(Request $request, Course $course): View
    {
        $this->authoriseOwner($request, $course);

        return view('assignments.create', compact('course'));
    }

    public function store(Request $request, Course $course): RedirectResponse
    {
        $this->authoriseOwner($request, $course);

        $assignment = $course->assignments()->create($this->validated($request));

        return redirect()->route('assignments.show', $assignment)->with('success', 'Assignment created.');
    }

    public function edit(Request $request, Assignment $assignment): View
    {
        $this->authoriseOwner($request, $assignment->course);

        return view('assignments.edit', compact('assignment'));
    }

    public function update(Request $request, Assignment $assignment): RedirectResponse
    {
        $this->authoriseOwner($request, $assignment->course);

        $assignment->update($this->validated($request));

        return redirect()->route('assignments.show', $assignment)->with('success', 'Assignment updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'due_date' => ['required', 'date'],
            'late_policy' => ['required', 'in:accept,close'],
        ]);

        return [
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'due_date' => $data['due_date'],
            'allow_late_submission' => $data['late_policy'] === 'accept',
        ];
    }


    public function show(Request $request, Assignment $assignment): View
    {
        $user = $request->user();
        $assignment->load('course');

        $isOwner = $assignment->course->instructor_id === $user->id;
        $isStudent = $user->can('assignment.submit') && $assignment->course->hasStudent($user);

        abort_unless($isOwner || $isStudent, 403);

        return view('assignments.show', [
            'assignment' => $assignment,
            'isOwner' => $isOwner,
            'submissions' => $isOwner
                ? $assignment->submissions()->with(['student', 'grade'])->get()
                : collect(),
            'mine' => $isStudent
                ? Submission::with('grade')
                    ->where('assignment_id', $assignment->id)
                    ->where('student_id', $user->id)
                    ->first()
                : null,
        ]);
    }

    public function destroy(Request $request, Assignment $assignment): RedirectResponse
    {
        $this->authoriseOwner($request, $assignment->course);

        $course = $assignment->course;
        $assignment->delete();

        return redirect()->route('courses.show', $course)->with('success', 'Assignment deleted.');
    }

    private function authoriseOwner(Request $request, Course $course): void
    {
        abort_unless($request->user()->can('assignment.create'), 403);
        abort_unless($course->instructor_id === $request->user()->id, 403, 'This course belongs to another instructor.');
    }
}
