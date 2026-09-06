<?php

/**
 * LearnSync -- Facade pattern: subsystem collaborator
 *
 * Module 1: Identity, Access & Digital Credentialing
 *
 * @author Serena Lim Sze Kee
 */

namespace App\Patterns\Facade\Subsystem;

use App\Models\Badge;
use App\Models\User;
use App\Support\Notifier;
use Illuminate\Database\Eloquent\Collection;

/**
 * SUBSYSTEM COMPONENT -- awarding badges from the award-rule registry
 * (EduSystem.md 1D).
 *
 * One of the collaborators hidden behind the CredentialAuthority Facade.
 *
 * This class decides WHO GETS A BADGE. It does not decide what the conditions
 * mean -- that is AwardConditionEvaluator's job, and it is deliberately the
 * same object the certificate rules ask, so a condition cannot mean one thing
 * for a badge and another for a certificate.
 *
 * The rule registry is loaded once and held on THIS INSTANCE for as long as the
 * instance lives, so every evaluation in a request reads the same rule set and
 * a rule cannot change underneath a student midway through their awards. That
 * is an ordinary instance property, not static state: two evaluators
 * constructed in a test are genuinely independent.
 */
class BadgeRuleEvaluator
{
    /**
     * Switchable by the recipient, like every other notification type.
     */
    public const TYPE_BADGE_AWARDED = 'badge.awarded';

    /**
     * The badge rules, loaded once per instance.
     *
     * @var Collection<int, Badge>|null
     */
    private ?Collection $badgeRules = null;

    public function __construct(private AwardConditionEvaluator $conditions)
    {
    }

    /**
     * Evaluate every active badge rule for a student and award those newly
     * satisfied.
     *
     * Safe to call repeatedly: already-earned badges are skipped, and the
     * composite unique key on badge_student is the database-level backstop.
     *
     * @return Collection<int, Badge> the badges awarded by this call
     */
    public function evaluate(User $student): Collection
    {
        $alreadyEarned = $student->badges()->pluck('badges.id')->all();
        $awarded = new Collection();

        foreach ($this->badgeRules() as $badge) {
            if (in_array($badge->id, $alreadyEarned, true)) {
                continue;
            }

            if (! $this->conditions->isSatisfied($student, $badge)) {
                continue;
            }

            $student->badges()->attach($badge->id, ['awarded_at' => now()]);
            $awarded->push($badge);

            /*
             * Told explicitly rather than by an observer, and this is the one
             * award that has to be.
             *
             * Every other notification in the system rides on an Eloquent
             * `created` event, but a badge is awarded by attaching a pivot row,
             * and attach() fires no model event at all -- there is no
             * BadgeStudent model for an observer to watch. So the send lives
             * here, next to the award it announces.
             *
             * The reference makes it idempotent, matching the guarantee the
             * rest of the registry gives: no student is told twice about one
             * badge, however often the engine runs.
             */
            Notifier::send(
                $student->id,
                self::TYPE_BADGE_AWARDED,
                "You have earned the \"{$badge->name}\" badge",
                route('badges.cabinet'),
                'badge:'.$badge->id,
            );
        }

        return $awarded;
    }

    /**
     * The active BADGE rules, read from the database exactly once per instance.
     *
     * Filtered to award_type 'badge': a certificate rule lives in the same
     * table and must not be handed out as a badge.
     *
     * @return Collection<int, Badge>
     */
    private function badgeRules(): Collection
    {
        return $this->badgeRules ??= Badge::where('is_active', true)
            ->where('award_type', 'badge')
            ->get();
    }
}
