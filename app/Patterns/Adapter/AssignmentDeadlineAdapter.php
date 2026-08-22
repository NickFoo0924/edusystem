<?php

namespace App\Patterns\Adapter;

use App\Models\Assignment;
use Carbon\CarbonInterface;

/**
 * ADAPTEE: Assignment. Not a diary entry at all -- a piece of work with a
 * deadline, which this adapter presents as though it were one.
 *
 * The mismatch the Adapter is absorbing: an assignment has no end time, no
 * room and no link, and its date means "by" rather than "at".
 */
class AssignmentDeadlineAdapter implements CalendarEntry
{
    public function __construct(private Assignment $assignment)
    {
    }

    public function title(): string
    {
        return $this->assignment->title;
    }

    public function startsAt(): CarbonInterface
    {
        return $this->assignment->due_date;
    }

    /**
     * A deadline is a moment. Reporting a fake end time would draw it as a
     * block on the grid and imply a duration nobody stated.
     */
    public function endsAt(): ?CarbonInterface
    {
        return null;
    }

    public function kind(): string
    {
        return 'Assignment due';
    }

    public function url(): ?string
    {
        return route('assignments.show', $this->assignment->id);
    }

    public function courseLabel(): ?string
    {
        return $this->assignment->course?->code;
    }

    public function detail(): string
    {
        return 'Due '.$this->assignment->due_date->format('g:ia');
    }

    /**
     * Amber, so a deadline never reads as just another class on the grid.
     */
    public function classes(): string
    {
        return 'border-amber-300 bg-amber-50 text-amber-900';
    }
}
