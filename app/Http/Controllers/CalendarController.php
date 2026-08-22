<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\CourseEvent;
use App\Patterns\Adapter\CalendarAdapterFactory;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;

/**
 * MODULE 2 (Foo Chong Xian) -- the calendar.
 *
 * Reads two sources and shows one grid. Everything it hands the view is a
 * CalendarEntry, so the template never learns that half its rows came from
 * `course_events` and half from `assignments.due_date`
 * (app/Patterns/Adapter, EduSystem.md Section 2).
 */
class CalendarController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        // The month on display, defaulting to this one. Anything unparseable
        // falls back rather than erroring: a hand-edited URL should not 500.
        try {
            $month = $request->filled('month')
                ? Carbon::createFromFormat('Y-m', $request->query('month'))->startOfMonth()
                : Carbon::now()->startOfMonth();
        } catch (\Throwable) {
            $month = Carbon::now()->startOfMonth();
        }

        /*
         * The grid always shows whole weeks, so it runs from the Sunday on or
         * before the 1st to the Saturday on or after the last day. Those
         * leading and trailing days belong to neighbouring months and their
         * entries have to be fetched too, or the first row looks empty.
         */
        $gridStart = $month->copy()->startOfMonth()->startOfWeek(Carbon::SUNDAY);
        $gridEnd = $month->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY);

        $events = CourseEvent::visibleTo($user)
            ->between($gridStart, $gridEnd)
            ->with('course')
            ->get();

        $assignments = $this->visibleAssignments($request, $gridStart, $gridEnd);

        $entriesByDay = CalendarAdapterFactory::groupedByDay($events, $assignments);

        // Days as one flat list; the view chunks it into weeks of seven.
        $days = collect();
        for ($day = $gridStart->copy(); $day->lte($gridEnd); $day->addDay()) {
            $days->push($day->copy());
        }

        /*
         * "What is coming up" answers the question people actually open a
         * calendar with, and it is not month-bound -- a deadline three days
         * away should not disappear because you paged back to last month.
         */
        $upcoming = CalendarAdapterFactory::merge(
            CourseEvent::visibleTo($user)
                ->between(Carbon::now(), Carbon::now()->addDays(14))
                ->with('course')->get(),
            $this->visibleAssignments($request, Carbon::now(), Carbon::now()->addDays(14))
        )->take(8);

        return view('calendar.index', [
            'month' => $month,
            'days' => $days,
            'entriesByDay' => $entriesByDay,
            'upcoming' => $upcoming,
            'today' => Carbon::today(),
            'canSchedule' => $user->can('event.manage'),
        ]);
    }

    /**
     * Assignment deadlines in a window, scoped exactly like the events are:
     * enrolled courses for a student, own courses for an instructor,
     * everything for an administrator.
     *
     * @return \Illuminate\Support\Collection<int, Assignment>
     */
    private function visibleAssignments(Request $request, $from, $to)
    {
        $user = $request->user();

        return Assignment::whereBetween('due_date', [$from, $to])
            ->where(function ($query) use ($user) {
                if ($user->can('analytics.view_system')) {
                    return;
                }

                $courseIds = collect();

                if ($user->can('course.enroll')) {
                    $courseIds = $courseIds->merge($user->courses()->pluck('courses.id'));
                }

                if ($user->can('course.create')) {
                    $courseIds = $courseIds->merge($user->coursesTeaching()->pluck('id'));
                }

                $query->whereIn('course_id', $courseIds->unique()->all());
            })
            ->with('course')
            ->get();
    }

    /*
     * Scheduling. Instructors schedule for the courses they own; an
     * administrator can also schedule institution-wide, which is the same
     * split announcements use.
     *
     * Assignment deadlines are deliberately not created here -- they already
     * exist as assignments.due_date, and the calendar adapts them rather than
     * storing a second copy that could disagree.
     */
    public function createEvent(Request $request): View
    {
        abort_unless($request->user()->can('event.manage'), 403);

        return view('calendar.create', [
            'courses' => $this->writableCourses($request),
            'canScheduleGlobally' => $request->user()->can('analytics.view_system'),
            'types' => CourseEvent::TYPES,
        ]);
    }

    public function storeEvent(Request $request): RedirectResponse
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

    public function destroyEvent(Request $request, CourseEvent $event): RedirectResponse
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
