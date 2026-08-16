<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * MODULE 1 -- the admin-configurable numbers (EduSystem.md 1B, Section 3 #18).
 *
 * These are the values the specification insists must not be magic numbers in
 * code: the progress weighting and the certificate pass threshold. The
 * CredentialAuthority reads them on every recalculation, so a change here
 * alters how progress is scored from the next grade onwards.
 */
class SettingController extends Controller
{
    /**
     * key => [label, help, min, max]
     */
    public const EDITABLE = [
        'progress.quiz_weight' => ['Quiz weight', 'Share of course progress earned by passing quizzes.', 0, 100],
        'progress.assignment_weight' => ['Assignment weight', 'Share earned by submitting assignments.', 0, 100],
        'progress.participation_weight' => ['Participation weight', 'Share earned by taking part in the course forum.', 0, 100],
        'certificate.pass_threshold' => ['Certificate pass threshold', 'Progress a student must reach before a certificate is issued.', 1, 100],
    ];

    public function edit(Request $request): View
    {
        abort_unless($request->user()->can('setting.manage'), 403);

        return view('settings.edit', [
            'editable' => self::EDITABLE,
            'values' => Setting::pluck('value', 'key'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('setting.manage'), 403);

        /*
         * The setting keys contain dots ("progress.quiz_weight"), and Laravel
         * reads a dot in a validation rule as array nesting -- so a rule named
         * "settings.progress.quiz_weight" would look for
         * $settings['progress']['quiz_weight'] and never match. The array is
         * therefore validated as a whole and each value checked by hand.
         */
        $request->validate([
            'settings' => ['required', 'array'],
            'settings.*' => ['required', 'numeric'],
        ]);

        $submitted = [];

        foreach (self::EDITABLE as $key => [$label, $help, $min, $max]) {
            $value = $request->input('settings.'.$key)
                ?? ($request->input('settings')[$key] ?? null);

            if ($value === null || ! is_numeric($value) || $value < $min || $value > $max) {
                return back()->withInput()
                    ->with('error', "\"{$label}\" must be a number between {$min} and {$max}.");
            }

            $submitted[$key] = (float) $value;
        }

        // The three weights are normalised at read time, so they need not total
        // exactly 100 -- but warn, because it is almost always a mistake.
        $weightTotal = $submitted['progress.quiz_weight']
            + $submitted['progress.assignment_weight']
            + $submitted['progress.participation_weight'];

        foreach ($submitted as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => (string) $value]);
        }

        ActivityLog::record('setting.updated');

        $message = 'Settings saved.';
        if (abs($weightTotal - 100) > 0.001) {
            $message .= " The three weights total {$weightTotal}, not 100 — progress is still scored proportionally, but check that is what you intended.";
        }

        return redirect()->route('settings.edit')->with('success', $message);
    }
}
