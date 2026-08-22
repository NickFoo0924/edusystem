<?php

namespace App\Patterns\Adapter;

use Carbon\CarbonInterface;

/**
 * MODULE 2 DESIGN PATTERN -- ADAPTER (Structural). A second TARGET interface.
 *
 * A calendar has to show two things that have nothing in common. A CourseEvent
 * is something somebody scheduled: it has a start, an end, a room or a meeting
 * link. An Assignment is not a diary entry at all -- it is a piece of work with
 * a due_date, no duration and no location, and its "time" is a deadline rather
 * than an appointment.
 *
 * Copying deadlines into the events table would make them agree only until the
 * first time an instructor moved one. So neither model changes: each is wrapped
 * in an adapter exposing this interface, and the month grid iterates one list
 * calling the same methods on every entry, with no idea which is which.
 *
 * This is the same pattern Module 2 already applies to course materials
 * (DisplayableMaterial), applied to a second pair of mismatched sources.
 */
interface CalendarEntry
{
    /**
     * What appears on the day cell.
     */
    public function title(): string;

    /**
     * When it happens, or is due.
     */
    public function startsAt(): CarbonInterface;

    /**
     * When it finishes. Null for a deadline, which is a moment, not a span.
     */
    public function endsAt(): ?CarbonInterface;

    /**
     * Short label for the kind of entry, e.g. "Class" or "Assignment due".
     */
    public function kind(): string;

    /**
     * Where clicking it goes, or null if there is nowhere useful to send them.
     */
    public function url(): ?string;

    /**
     * The course code this belongs to, or null for an institution-wide event.
     */
    public function courseLabel(): ?string;

    /**
     * The supporting line: a time range, a room, or a meeting link's host.
     */
    public function detail(): string;

    /**
     * Tailwind classes colouring this kind of entry in the grid.
     */
    public function classes(): string;
}
