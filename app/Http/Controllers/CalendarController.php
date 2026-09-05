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

    /**
     * One scheduled event, in full.
     *
     * Clicking an entry on the grid comes here rather than jumping straight to
     * the meeting link. Two reasons, and the second is the one that matters:
     * being thrown into a live video call by a misclick is unpleasant, and not
     * every calendar entry HAS a link -- a room-based class and an assignment
     * deadline both have nowhere to jump to, so a direct-redirect design would
     * have to special-case them anyway.
     */
    public function showEvent(Request $request, CourseEvent $event): View
    {
        /*
         * ACCESS CONTROL.
         *
         * Guessing an id must reveal nothing the calendar would not already
         * have shown you, so this asks exactly the same question the grid does
         * -- the visibleTo scope -- rather than a second, looser rule that
         * could drift away from it. A student who is not enrolled in the
         * course, and any instructor who does not teach it, gets a 403.
         */
        abort_unless(
            CourseEvent::visibleTo($request->user())->whereKey($event->getKey())->exists(),
            403,
            'That event belongs to a course you are not part of.'
        );

        $event->load(['course.instructor', 'creator']);

        return view('calendar.show', [
            'event' => $event,
            // Null unless there is a genuinely usable link -- see safeMeetingUrl.
            'joinUrl' => $this->safeMeetingUrl($event->meeting_url),
            // A broken link is worth saying out loud rather than silently
            // dropping, so whoever scheduled it can fix it.
            'meetingUrlIsBroken' => filled($event->meeting_url) && $this->safeMeetingUrl($event->meeting_url) === null,
            'audience' => $this->audienceFor($request, $event),
            'canDelete' => $request->user()->can('event.manage')
                && ($event->created_by === $request->user()->id || $request->user()->can('analytics.view_system')),
        ]);
    }

    /**
     * A meeting link only if it is genuinely usable, otherwise null.
     *
     * storeEvent() validates `url` on the way in, but a row can also arrive
     * from a seeder, a migration or somebody editing the database by hand, and
     * this page must not crash or -- worse -- render whatever it finds as a
     * clickable button.
     *
     * The scheme check is the security half: `javascript:` and `data:` URLs
     * satisfy FILTER_VALIDATE_URL, and turning one into a "Join meeting" button
     * would hand every viewer a scripted link dressed as a video call.
     */
    private function safeMeetingUrl(?string $url): ?string
    {
        if (blank($url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) ? $url : null;
    }

    /**
     * Who the event concerns, described at the level the viewer is entitled to.
     *
     * Names are shown to whoever runs the class -- the course's instructor and
     * an administrator -- and a count to everybody else. That is the same line
     * the course roster already draws: a student cannot browse the people they
     * study alongside, so a calendar page must not become the way around it.
     *
     * @return array{label: string, names: \Illuminate\Support\Collection<int, string>, count: int}
     */
    private function audienceFor(Request $request, CourseEvent $event): array
    {
        if ($event->isGlobal()) {
            return [
                'label' => 'Everyone at the institution',
                'names' => collect(),
                'count' => 0,
            ];
        }

        $course = $event->course;

        if ($course === null) {
            return ['label' => 'Nobody — the course has been removed', 'names' => collect(), 'count' => 0];
        }

        $students = $course->students()->orderBy('name')->get();
        $maySeeNames = $request->user()->can('analytics.view_system')
            || $course->instructor_id === $request->user()->id;

        return [
            'label' => $course->label(),
            'names' => $maySeeNames ? $students->pluck('name') : collect(),
            'count' => $students->count(),
        ];
    }

    public function destroyEvent(Request $request, CourseEvent $event): RedirectResponse
    {
        abort_unless($request->user()->can('event.manage'), 403);

        $allowed = $event->created_by === $request->user()->id
            || $request->user()->can('analytics.view_system');

        abort_unless($allowed, 403);

        $month = $event->starts_at->format('Y-m');
        $event->delete();

        /*
         * Explicitly to the calendar rather than back(): deletion is now also
         * reachable from the event's own page, and going "back" to a record
         * that no longer exists would 404 on the model binding.
         */
        return redirect()
            ->route('calendar.index', ['month' => $month])
            ->with('success', 'Event removed from the calendar.');
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
