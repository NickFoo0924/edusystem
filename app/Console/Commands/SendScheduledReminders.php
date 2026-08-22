<?php

namespace App\Console\Commands;

use App\Models\Assignment;
use App\Models\CourseEvent;
use App\Models\Submission;
use App\Models\User;
use App\Support\Notifier;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * MODULE 3 -- the reminders that make the calendar useful rather than merely
 * accurate.
 *
 * Deliberately NOT an Observer, and worth being clear about why. Module 3's
 * Observer fires when a model is saved: something happened, so tell someone.
 * Nothing is saved when a deadline approaches -- the passage of time is not an
 * Eloquent event, and there is no subject to observe. So this is a scheduled
 * producer feeding the same inbox, through the same Notifier, honouring the
 * same preferences.
 *
 * It reads Module 2's calendar sources and writes Module 3's notifications,
 * which is the boundary those two modules already have (EduSystem.md 2A).
 *
 * Safe to run as often as you like: every reminder carries a reference, and
 * the Notifier refuses to tell the same person the same thing twice.
 */
class SendScheduledReminders extends Command
{
    protected $signature = 'reminders:send
                            {--event-window=60 : Minutes ahead to warn about a class or meeting}
                            {--due-window=24 : Hours ahead to warn a student about a deadline}';

    protected $description = 'Remind people about imminent classes, meetings and deadlines';

    /**
     * Notification type keys, switchable per user in notification preferences.
     */
    public const TYPE_EVENT_SOON = 'event.reminder';

    public const TYPE_ASSIGNMENT_DUE = 'assignment.due_soon';

    public const TYPE_ASSIGNMENT_CLOSED = 'assignment.closed';

    public function handle(): int
    {
        $sent = $this->remindAboutEvents((int) $this->option('event-window'))
            + $this->remindStudentsOfDeadlines((int) $this->option('due-window'))
            + $this->tellInstructorsWorkIsReadyToMark();

        $this->info($sent === 0 ? 'Nothing to remind anyone about.' : "Sent {$sent} reminder(s).");

        return self::SUCCESS;
    }

    /**
     * "Your class starts in 40 minutes" -- to everyone the event applies to,
     * the lecturer included, since being late to your own class is worse.
     */
    private function remindAboutEvents(int $windowMinutes): int
    {
        $events = CourseEvent::with('course')
            ->whereBetween('starts_at', [Carbon::now(), Carbon::now()->addMinutes($windowMinutes)])
            ->get();

        $sent = 0;

        foreach ($events as $event) {
            $when = $event->starts_at->diffForHumans(['parts' => 1]);
            $where = $event->meeting_url ? ' (online)' : ($event->location ? " in {$event->location}" : '');
            $label = $event->course?->code ? $event->course->code.' — ' : '';

            $message = "{$label}{$event->title} starts {$when}{$where}";
            $link = $event->meeting_url ?: route('calendar.index');

            foreach ($this->audienceFor($event) as $userId) {
                $sent += Notifier::send(
                    $userId, self::TYPE_EVENT_SOON, $message, $link, 'event:'.$event->id
                ) ? 1 : 0;
            }
        }

        return $sent;
    }

    /**
     * "This is due tomorrow and you have not handed it in."
     *
     * Only students who have actually not submitted are told. Reminding
     * somebody to do a thing they have already done is how people learn to
     * ignore notifications.
     */
    private function remindStudentsOfDeadlines(int $windowHours): int
    {
        $assignments = Assignment::with('course')
            ->whereBetween('due_date', [Carbon::now(), Carbon::now()->addHours($windowHours)])
            ->get();

        $sent = 0;

        foreach ($assignments as $assignment) {
            if (! $assignment->course) {
                continue;
            }

            // A draft is not a submission: submitted_at is what counts.
            $handedIn = Submission::where('assignment_id', $assignment->id)
                ->whereNotNull('submitted_at')
                ->pluck('student_id');

            $outstanding = $assignment->course->students()
                ->whereNotIn('users.id', $handedIn)
                ->pluck('users.id');

            $message = "{$assignment->course->code} — \"{$assignment->title}\" is due "
                .$assignment->due_date->diffForHumans(['parts' => 1])
                .'. You have not submitted yet.';

            foreach ($outstanding as $studentId) {
                $sent += Notifier::send(
                    $studentId,
                    self::TYPE_ASSIGNMENT_DUE,
                    $message,
                    route('assignments.show', $assignment->id),
                    'assignment_due:'.$assignment->id
                ) ? 1 : 0;
            }
        }

        return $sent;
    }

    /**
     * "That deadline has passed -- there is work waiting for you."
     *
     * Bounded to deadlines that closed in the last day so a fresh install does
     * not open with a notification for every assignment ever set.
     */
    private function tellInstructorsWorkIsReadyToMark(): int
    {
        $assignments = Assignment::with('course')
            ->whereBetween('due_date', [Carbon::now()->subDay(), Carbon::now()])
            ->get();

        $sent = 0;

        foreach ($assignments as $assignment) {
            if (! $assignment->course) {
                continue;
            }

            $waiting = Submission::where('assignment_id', $assignment->id)
                ->whereNotNull('submitted_at')
                ->count();

            $message = "{$assignment->course->code} — \"{$assignment->title}\" has closed. "
                .($waiting === 1 ? '1 submission is' : "{$waiting} submissions are")
                .' ready to review.';

            $sent += Notifier::send(
                $assignment->course->instructor_id,
                self::TYPE_ASSIGNMENT_CLOSED,
                $message,
                route('assignments.show', $assignment->id),
                'assignment_closed:'.$assignment->id
            ) ? 1 : 0;
        }

        return $sent;
    }

    /**
     * Who an event concerns: its course's students and lecturer, or -- for an
     * institution-wide event -- every active account.
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function audienceFor(CourseEvent $event)
    {
        if ($event->isGlobal()) {
            return User::query()->pluck('id');
        }

        if (! $event->course) {
            return collect();
        }

        return $event->course->students()->pluck('users.id')
            ->push($event->course->instructor_id)
            ->unique()
            ->values();
    }
}
