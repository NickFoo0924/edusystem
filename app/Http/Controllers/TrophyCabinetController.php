<?php

namespace App\Http\Controllers;

use App\Models\Badge;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The student's trophy cabinet (EduSystem.md 1D).
 *
 * Every active badge is shown: the ones earned in full colour, the rest greyed
 * out with their unlock condition on display, so a student can see what is
 * still available to work towards.
 */
class TrophyCabinetController extends Controller
{
    /**
     * Rank used to order the cabinet, since the tier column is an enum whose
     * alphabetical order (bronze, gold, silver) is not its value order.
     */
    private const TIER_RANK = ['bronze' => 1, 'silver' => 2, 'gold' => 3];

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('progress.view_own'), 403);

        $student = $request->user();

        // Keyed by badge id so the view can look up the awarded_at pivot.
        $earned = $student->badges()->get()->keyBy('id');

        $badges = Badge::where('is_active', true)
            ->get()
            ->sortBy([
                // Earned first, then bronze -> silver -> gold.
                fn (Badge $a, Badge $b) => (int) $earned->has($b->id) <=> (int) $earned->has($a->id),
                fn (Badge $a, Badge $b) => self::TIER_RANK[$a->tier] <=> self::TIER_RANK[$b->tier],
            ]);

        return view('badges.cabinet', [
            'badges' => $badges,
            'earned' => $earned,
        ]);
    }
}
