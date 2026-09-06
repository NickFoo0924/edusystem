<?php

/**
 * LearnSync -- Support helper
 *
 * Module 3: Student Forum & Notifications
 *
 * @author Ong Shun Yan
 */

namespace App\Support;

use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Finds the people named with an @ in a forum message.
 *
 * Candidates come from the course itself -- its students and its lecturer --
 * so a mention can never reach somebody who is not in the conversation, and a
 * name typed by a student cannot be used to notify a stranger.
 *
 * Three ways of writing the same person are accepted, because names here have
 * spaces in them and an @handle cannot:
 *
 *     @FooChongXian   the full name with the spaces closed up
 *     @Foo            the first name, when it is unambiguous in this course
 *     @student12      the account's email local part
 *
 * Ambiguity resolves to nobody. If two people in a course are both "Ong",
 * @Ong notifies neither, which is quieter than guessing and notifying the
 * wrong one.
 */
class Mentions
{
    /**
     * Handles as they may be typed, mapped to the user they mean.
     *
     * A handle that more than one person answers to is dropped entirely.
     *
     * @return Collection<string, User>  lower-cased handle => user
     */
    public static function candidates(Course $course): Collection
    {
        $people = $course->students()->get();

        if ($course->instructor) {
            $people = $people->push($course->instructor);
        }

        $claims = [];

        foreach ($people->unique('id') as $person) {
            foreach (self::handlesFor($person) as $handle) {
                $claims[$handle][] = $person;
            }
        }

        return collect($claims)
            ->reject(fn (array $owners) => count($owners) > 1)
            ->map(fn (array $owners) => $owners[0]);
    }

    /**
     * The users actually named in a message.
     *
     * @return Collection<int, User>
     */
    public static function parse(string $body, Course $course): Collection
    {
        preg_match_all('/@([A-Za-z0-9._-]{2,60})/', $body, $matches);

        if (empty($matches[1])) {
            return collect();
        }

        $candidates = self::candidates($course);

        return collect($matches[1])
            ->map(fn (string $handle) => $candidates->get(strtolower($handle)))
            ->filter()
            ->unique('id')
            ->values();
    }

    /**
     * Turn the @handles in a message into highlighted spans.
     *
     * The body is escaped first and only then marked up, so a message
     * containing HTML is still shown as text.
     */
    public static function highlight(string $body, Course $course): string
    {
        $candidates = self::candidates($course);
        $escaped = e($body);

        return preg_replace_callback(
            '/@([A-Za-z0-9._-]{2,60})/',
            function (array $m) use ($candidates) {
                if (! $candidates->has(strtolower($m[1]))) {
                    return $m[0];
                }

                return '<span class="rounded bg-blue-50 px-1 font-medium text-blue-700">'.$m[0].'</span>';
            },
            $escaped
        );
    }

    /**
     * Every way one person may be addressed.
     *
     * @return array<int, string>
     */
    private static function handlesFor(User $user): array
    {
        $handles = [];

        $closedUp = preg_replace('/[^A-Za-z0-9]/', '', $user->name);
        if (strlen($closedUp) >= 2) {
            $handles[] = strtolower($closedUp);
        }

        $first = preg_replace('/[^A-Za-z0-9]/', '', strtok($user->name, ' '));
        if (strlen($first) >= 2) {
            $handles[] = strtolower($first);
        }

        $local = strstr($user->email, '@', true);
        if ($local && strlen($local) >= 2) {
            $handles[] = strtolower($local);
        }

        return array_unique($handles);
    }
}
