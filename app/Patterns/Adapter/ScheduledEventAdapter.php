<?php

/**
 * LearnSync -- Adapter pattern (structural)
 *
 * Module 2: Academic Resources Repository
 *
 * @author Foo Chong Xian
 */

namespace App\Patterns\Adapter;

use App\Models\CourseEvent;
use Carbon\CarbonInterface;

/**
 * ADAPTEE: CourseEvent. Something somebody put in the diary.
 */
class ScheduledEventAdapter implements CalendarEntry
{
    public function __construct(private CourseEvent $event)
    {
    }

    public function title(): string
    {
        return $this->event->title;
    }

    public function startsAt(): CarbonInterface
    {
        return $this->event->starts_at;
    }

    public function endsAt(): ?CarbonInterface
    {
        return $this->event->ends_at;
    }

    public function kind(): string
    {
        return CourseEvent::TYPES[$this->event->type] ?? 'Event';
    }

    /**
     * The event's own page, always -- never the meeting link directly.
     *
     * This used to return meeting_url when there was one, which meant a single
     * click on the grid dropped you into a live video call with no warning, and
     * a misclick put you in front of a class. It also could not describe an
     * event that has no link at all: a room-based class had to fall back to the
     * course page, so the same control did two different things depending on
     * data the person clicking could not see.
     *
     * Now every entry goes to its detail page, which shows the times, the room,
     * the description and who it concerns -- and offers "Join meeting" as a
     * deliberate second click when there is something to join.
     */
    public function url(): ?string
    {
        return route('events.show', $this->event->id);
    }

    public function courseLabel(): ?string
    {
        return $this->event->course?->code;
    }

    public function detail(): string
    {
        $parts = [$this->timeRange()];

        if ($this->event->location) {
            $parts[] = $this->event->location;
        }

        if ($this->event->meeting_url) {
            // The host alone, since a full meeting URL is unreadable in a cell.
            $parts[] = parse_url($this->event->meeting_url, PHP_URL_HOST) ?: 'online';
        }

        return implode(' · ', $parts);
    }

    public function classes(): string
    {
        return match ($this->event->type) {
            'meeting' => 'border-violet-300 bg-violet-50 text-violet-900',
            'other' => 'border-gray-300 bg-gray-50 text-gray-800',
            default => 'border-blue-300 bg-blue-50 text-blue-900',
        };
    }

    private function timeRange(): string
    {
        $start = $this->event->starts_at->format('g:ia');

        return $this->event->ends_at
            ? $start.'–'.$this->event->ends_at->format('g:ia')
            : $start;
    }
}
