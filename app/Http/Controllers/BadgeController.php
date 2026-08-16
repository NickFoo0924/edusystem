<?php

namespace App\Http\Controllers;

use App\Models\Badge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Administrator management of the badge rule registry (EduSystem.md 1D).
 *
 * This screen is what makes "badges are rules configured by an Administrator,
 * never hardcoded" true: the criteria live in table rows an admin edits here,
 * and the CredentialAuthority reads whatever it finds.
 *
 * Guarded by the badge.manage permission key, never by a role comparison. The
 * guard is applied as `can:badge.manage` route middleware in routes/web.php,
 * which resolves through the same database-backed Gate.
 */
class BadgeController extends Controller
{
    /**
     * The criteria the rules engine understands, and what criteria_value means
     * for each. Kept here so the form and the validator cannot drift apart.
     */
    public const CRITERIA_TYPES = [
        'course_completion' => 'Courses completed (value = how many)',
        'path_completion' => 'Learning paths completed (value = how many)',
        'quiz_score' => 'Quiz score reached (value = percentage)',
        'on_time_submissions' => 'Assignments submitted on time (value = how many)',
        'first_forum_post' => 'First forum post (value = 1)',
        'login_streak' => 'Consecutive days logged in (value = how many)',
    ];

    public const TIERS = ['bronze', 'silver', 'gold'];

    public function index(): View
    {
        $badges = Badge::withCount('students')->orderBy('name')->get();

        return view('badges.index', compact('badges'));
    }

    public function create(): View
    {
        return view('badges.create', [
            'criteriaTypes' => self::CRITERIA_TYPES,
            'tiers' => self::TIERS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        if ($request->hasFile('icon')) {
            $data['icon_path'] = $this->storeIcon($request->file('icon'));
        }

        $badge = Badge::create($data);

        return redirect()->route('badges.index')
            ->with('success', "Badge \"{$badge->name}\" created.");
    }

    public function edit(Badge $badge): View
    {
        return view('badges.edit', [
            'badge' => $badge,
            'criteriaTypes' => self::CRITERIA_TYPES,
            'tiers' => self::TIERS,
        ]);
    }

    public function update(Request $request, Badge $badge): RedirectResponse
    {
        $data = $this->validated($request);

        if ($request->hasFile('icon')) {
            $this->deleteIcon($badge);
            $data['icon_path'] = $this->storeIcon($request->file('icon'));
        }

        $badge->update($data);

        return redirect()->route('badges.index')
            ->with('success', "Badge \"{$badge->name}\" updated.");
    }

    public function destroy(Badge $badge): RedirectResponse
    {
        $name = $badge->name;

        // badge_student rows cascade with the badge, so previously awarded
        // copies of a deleted rule disappear from every trophy cabinet.
        $this->deleteIcon($badge);
        $badge->delete();

        return redirect()->route('badges.index')
            ->with('success', "Badge \"{$name}\" deleted.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:255'],
            'tier' => ['required', 'in:'.implode(',', self::TIERS)],
            'criteria_type' => ['required', 'in:'.implode(',', array_keys(self::CRITERIA_TYPES))],
            'criteria_value' => ['required', 'integer', 'min:1'],
            'icon' => ['nullable', 'image', 'max:2048'],
        ]);

        // An unchecked checkbox is simply absent from the request.
        $data['is_active'] = $request->boolean('is_active');
        unset($data['icon']);

        return $data;
    }

    /**
     * Normalise an uploaded icon to a square 128px PNG so the cabinet grid
     * stays even regardless of what the administrator uploads.
     */
    private function storeIcon(UploadedFile $file): string
    {
        $image = (new ImageManager(new Driver()))->read($file->getRealPath());
        $image->cover(128, 128);

        $path = 'badges/'.uniqid('badge_', true).'.png';
        Storage::disk('public')->put($path, (string) $image->toPng());

        return $path;
    }

    private function deleteIcon(Badge $badge): void
    {
        if (filled($badge->icon_path) && Storage::disk('public')->exists($badge->icon_path)) {
            Storage::disk('public')->delete($badge->icon_path);
        }
    }
}
