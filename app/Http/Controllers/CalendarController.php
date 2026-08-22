<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\CourseEvent;
use App\Patterns\Adapter\CalendarAdapterFactory;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

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
}
