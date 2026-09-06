<?php

/**
 * LearnSync -- Adapter pattern (structural)
 *
 * Module 2: Academic Resources Repository
 *
 * @author Foo Chong Xian
 */

namespace App\Patterns\Adapter;

use App\Models\Assignment;
use App\Models\CourseEvent;
use Illuminate\Support\Collection;

/**
 * Wraps scheduled events and assignment deadlines into one sorted list of
 * CalendarEntry.
 *
 * This is the only place in the system that knows a calendar is fed from two
 * different tables. The controller asks for entries in a window; the view
 * iterates them. Neither branches on which kind it is holding, which is what
 * the Adapter buys us here -- and adding a third source later (an exam
 * timetable, say) means one more adapter and one more line below, with nothing
 * else touched.
 *
 * A plain static helper, not a second design pattern: Module 2's one GoF
 * pattern is the Adapter (EduSystem.md Section 2).
 */
class CalendarAdapterFactory
{
    public static function forEvent(CourseEvent $event): CalendarEntry
    {
        return new ScheduledEventAdapter($event);
    }

    public static function forAssignment(Assignment $assignment): CalendarEntry
    {
        return new AssignmentDeadlineAdapter($assignment);
    }

    /**
     * Both sources, adapted and ordered by when they happen.
     *
     * @param  iterable<CourseEvent>  $events
     * @param  iterable<Assignment>  $assignments
     * @return Collection<int, CalendarEntry>
     */
    public static function merge(iterable $events, iterable $assignments): Collection
    {
        return collect($events)->map(fn (CourseEvent $e) => self::forEvent($e))
            ->concat(collect($assignments)->map(fn (Assignment $a) => self::forAssignment($a)))
            ->sortBy(fn (CalendarEntry $entry) => $entry->startsAt()->getTimestamp())
            ->values();
    }

    /**
     * The same entries, bucketed by Y-m-d so a month grid can look up one day
     * without filtering the whole list per cell.
     *
     * @param  iterable<CourseEvent>  $events
     * @param  iterable<Assignment>  $assignments
     * @return Collection<string, Collection<int, CalendarEntry>>
     */
    public static function groupedByDay(iterable $events, iterable $assignments): Collection
    {
        return self::merge($events, $assignments)
            ->groupBy(fn (CalendarEntry $entry) => $entry->startsAt()->format('Y-m-d'));
    }
}
