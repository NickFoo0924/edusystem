<?php

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
     * A meeting link is the useful destination when there is one -- that is the
     * whole point of clicking an online class. Otherwise the course page.
     */
    public function url(): ?string
    {
        if ($this->event->meeting_url) {
            return $this->event->meeting_url;
        }

        return $this->event->course_id
            ? route('courses.show', $this->event->course_id)
            : null;
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
