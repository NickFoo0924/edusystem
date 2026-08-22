<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * MODULE 2 -- scheduling classes and meetings.
 *
 * Gated the way every other write to a course is: the permission key first,
 * then ownership, so one lecturer cannot put an event on another's timetable.
 * An administrator schedules institution-wide events, which is the same
 * privilege they hold over global announcements.
 */
class CourseEventController extends Controller
{
    public function create(Request $request): View
    {
        abort_unless($request->user()->can('event.manage'), 403);

        return view('calendar.create', [
            'courses' => $this->writableCourses($request),
            'canScheduleGlobally' => $request->user()->can('analytics.view_system'),
            'types' => CourseEvent::TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('event.manage'), 403);

        $data = $request->validate([
            'course_id' => ['nullable', 'exists:courses,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', Rule::in(array_keys(CourseEvent::TYPES))],
            'location' => ['nullable', 'string', 'max:255'],
            'meeting_url' => ['nullable', 'url', 'max:2048'],
            'starts_at' => ['required', 'date'],
            // An event that ends before it starts is the one date mistake worth
            // catching; everything else is the scheduler's business.
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ], [
            'ends_at.after' => 'The end time must be after the start time.',
        ]);

        if (blank($data['course_id'] ?? null)) {
            abort_unless($request->user()->can('analytics.view_system'), 403,
                'Only an administrator may schedule an institution-wide event.');
            $data['course_id'] = null;
        } else {
            abort_unless(
                $this->writableCourses($request)->contains('id', (int) $data['course_id']),
                403,
                'You cannot schedule an event for that course.'
            );
        }

        $event = CourseEvent::create($data + ['created_by' => $request->user()->id]);

        return redirect()
            ->route('calendar.index', ['month' => $event->starts_at->format('Y-m')])
            ->with('success', "Scheduled \"{$event->title}\".");
    }

    public function destroy(Request $request, CourseEvent $event): RedirectResponse
    {
        abort_unless($request->user()->can('event.manage'), 403);

        $allowed = $event->created_by === $request->user()->id
            || $request->user()->can('analytics.view_system');

        abort_unless($allowed, 403);

        $event->delete();

        return back()->with('success', 'Event removed from the calendar.');
    }

    /**
     * Courses this user may schedule for: their own if an instructor, all of
     * them if an administrator.
     */
    private function writableCourses(Request $request)
    {
        return $request->user()->can('analytics.view_system')
            ? Course::orderBy('code')->get()
            : $request->user()->coursesTeaching()->orderBy('code')->get();
    }
}
