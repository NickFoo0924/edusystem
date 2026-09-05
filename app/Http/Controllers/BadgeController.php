<?php

namespace App\Http\Controllers;

use App\Models\Badge;
use App\Models\CertificateTemplate;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
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
    /**
     * The conditions the rules engine understands, and what criteria_value
     * means for each. Kept here so the form and the validator cannot drift
     * apart, and deliberately a FIXED LIST rather than an expression language:
     * an administrator picks one and fills in its number, so no rule they can
     * write is able to error, loop, or read data they should not see.
     *
     * Adding one means an arm in AwardConditionEvaluator and a line in the
     * criteria_type enum. That is the price of not shipping an interpreter.
     */
    public const CRITERIA_TYPES = [
        'course_completion' => 'Courses completed (value = how many)',
        'path_completion' => 'Learning paths completed (value = how many)',
        'quiz_score' => 'Quiz score reached (value = percentage)',
        'on_time_submissions' => 'Assignments submitted on time (value = how many)',
        'first_forum_post' => 'First forum post (value = 1)',
        'login_streak' => 'Consecutive days logged in (value = how many)',
        'all_quizzes_in_course' => 'Every quiz in a subject passed (pick a subject, or leave as any and set value = how many subjects)',
        'average_score_in_course' => 'Average quiz score in a subject (value = percentage; pick a subject, or leave as any for all quizzes)',
        'quizzes_completed' => 'Quizzes passed in total, across every subject (value = how many)',
    ];

    /**
     * Conditions that can be narrowed to a single subject. The rest ignore
     * course_id entirely and the controller clears it for them.
     */
    public const SUBJECT_SCOPED_CRITERIA = [
        'all_quizzes_in_course',
        'average_score_in_course',
    ];

    /**
     * What satisfying a rule produces.
     */
    public const AWARD_TYPES = [
        'badge' => 'Badge — appears in the trophy cabinet',
        'certificate' => 'Certificate — mints a verifiable credential with a PDF and QR code',
    ];

    public const TIERS = ['bronze', 'silver', 'gold'];

    public function index(): View
    {
        $badges = Badge::with(['course', 'certificateTemplate'])
            ->withCount('students')
            ->orderBy('award_type')
            ->orderBy('name')
            ->get();

        return view('badges.index', compact('badges'));
    }

    /**
     * Turn a rule on or off without deleting it.
     *
     * Deactivating is the safe way to stop a rule: the engine skips inactive
     * rules, but every award already made from it stays exactly where it is
     * Deleting is the destructive option instead, and for
     * badges it cascades the awards away with the rule.
     */
    public function toggle(Badge $badge): RedirectResponse
    {
        $badge->update(['is_active' => ! $badge->is_active]);

        return back()->with(
            'success',
            "\"{$badge->name}\" is now ".($badge->is_active ? 'active' : 'inactive').'.'
        );
    }

    public function create(): View
    {
        return view('badges.create', [
            'criteriaTypes' => self::CRITERIA_TYPES,
            'tiers' => self::TIERS,
            'courses' => $this->selectableCourses(),
            'subjectScopedCriteria' => self::SUBJECT_SCOPED_CRITERIA,
            'awardTypes' => self::AWARD_TYPES,
            'templates' => CertificateTemplate::orderBy('name')->get(['id', 'name']),
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
            'courses' => $this->selectableCourses(),
            'subjectScopedCriteria' => self::SUBJECT_SCOPED_CRITERIA,
            'awardTypes' => self::AWARD_TYPES,
            'templates' => CertificateTemplate::orderBy('name')->get(['id', 'name']),
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
            'award_type' => ['required', 'in:'.implode(',', array_keys(self::AWARD_TYPES))],
            'course_id' => ['nullable', 'exists:courses,id'],
            'certificate_template_id' => ['nullable', 'exists:certificate_templates,id'],
            'icon' => ['nullable', 'image', 'max:2048'],
        ]);

        /*
         * A certificate attests to something in particular, so a certificate
         * rule must name the subject it is issued for. Validated here rather
         * than silently skipped at evaluation time, so an administrator finds
         * out when they save the rule rather than wondering later why it never
         * fires.
         */
        if ($data['award_type'] === 'certificate' && blank($data['course_id'] ?? null)) {
            throw ValidationException::withMessages([
                'course_id' => 'A certificate rule must name the subject its credential is issued for.',
            ]);
        }

        // An unchecked checkbox is simply absent from the request.
        $data['is_active'] = $request->boolean('is_active');
        unset($data['icon']);

        /*
         * Only some criteria are scoped to a subject. Clearing the column for
         * the rest means the form cannot leave a stale course behind when an
         * admin switches an existing rule from "every quiz in Integrative
         * Programming" to, say, a login streak -- which would otherwise sit in
         * the row meaning nothing.
         */
        if (! in_array($data['criteria_type'], self::SUBJECT_SCOPED_CRITERIA, true)
            && $data['award_type'] !== 'certificate') {
            $data['course_id'] = null;
        }

        // Likewise: a template is meaningless on a badge rule.
        if ($data['award_type'] !== 'certificate') {
            $data['certificate_template_id'] = null;
        }

        return $data;
    }

    /**
     * Courses an admin can scope a badge to, newest naming first.
     *
     * @return \Illuminate\Support\Collection<int, Course>
     */
    private function selectableCourses()
    {
        return Course::orderBy('code')->get(['id', 'code', 'title']);
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

    /*
     * The student's trophy cabinet -- the same badges seen from the other end.
     * Earned ones in colour, the rest greyed out with the condition that would
     * unlock them, which is what makes the rules feel like goals rather than
     * hidden machinery.
     */
    /**
     * Rank used to order the cabinet, since the tier column is an enum whose
     * alphabetical order (bronze, gold, silver) is not its value order.
     */
    private const TIER_RANK = ['bronze' => 1, 'silver' => 2, 'gold' => 3];

    public function cabinet(Request $request): View
    {
        abort_unless($request->user()->can('progress.view_own'), 403);

        $student = $request->user();

        // Keyed by badge id so the view can look up the awarded_at pivot.
        $earned = $student->badges()->get()->keyBy('id');

        // Badge rules only. A certificate rule lives in the same registry but
        // produces a credential, and belongs on My Certificates rather than in
        // a cabinet of medals.
        $badges = Badge::where('is_active', true)
            ->where('award_type', 'badge')
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
