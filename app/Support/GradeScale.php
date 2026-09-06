<?php

/**
 * LearnSync -- Support helper
 *
 * Module 5: Academic Progress Analytics
 *
 * @author Ong Kwong Wei
 */

namespace App\Support;

/**
 * The letter grade scale.
 *
 * Marks are stored as a percentage on `grades.calculated_score` and the letter
 * is derived here rather than stored. One source of truth: a mark and its
 * letter can never drift apart, and correcting the scale corrects every grade
 * in the system at once.
 *
 * A plain helper, not a design pattern -- Module 5's one GoF pattern is the
 * State pattern on Submission (EduSystem.md Section 2).
 */
final class GradeScale
{
    /**
     * letter => [inclusive minimum percentage, grade point]
     *
     * Ordered highest first, which is what letterFor() walks.
     */
    private const BANDS = [
        'A' => [80, 4.00],
        'A-' => [75, 3.67],
        'B+' => [70, 3.33],
        'B' => [65, 3.00],
        'B-' => [60, 2.67],
        'C+' => [55, 2.33],
        'C' => [50, 2.00],
        'C-' => [47, 1.67],
        'D+' => [44, 1.33],
        'D' => [40, 1.00],
        'F' => [0, 0.00],
    ];

    /**
     * The lowest passing mark. Below this is an F.
     */
    public const PASS_MARK = 40;

    /**
     * The letter for a mark, e.g. 88 -> "A", 62 -> "B-".
     */
    public static function letterFor(float $score): string
    {
        foreach (self::BANDS as $letter => [$minimum, $point]) {
            if ($score >= $minimum) {
                return $letter;
            }
        }

        return 'F';
    }

    /**
     * The grade point for a mark, e.g. 88 -> 4.00.
     */
    public static function pointFor(float $score): float
    {
        foreach (self::BANDS as $letter => [$minimum, $point]) {
            if ($score >= $minimum) {
                return $point;
            }
        }

        return 0.00;
    }

    /**
     * The letter without its modifier: A-, A both fall under "A".
     *
     * Used for the distribution chart, where eleven bars would be unreadable
     * but five tell the same story.
     */
    public static function familyFor(float $score): string
    {
        return rtrim(self::letterFor($score), '+-');
    }

    /**
     * The five families, highest first, for building a distribution.
     *
     * @return array<int, string>
     */
    public static function families(): array
    {
        return ['A', 'B', 'C', 'D', 'F'];
    }

    public static function isPass(float $score): bool
    {
        return $score >= self::PASS_MARK;
    }

    /**
     * Tailwind classes for a letter, so a grade reads the same everywhere it
     * appears.
     */
    public static function classesFor(float $score): string
    {
        return match (self::familyFor($score)) {
            'A' => 'bg-emerald-100 text-emerald-800',
            'B' => 'bg-blue-100 text-blue-800',
            'C' => 'bg-amber-100 text-amber-800',
            'D' => 'bg-orange-100 text-orange-800',
            default => 'bg-red-100 text-red-800',
        };
    }

    /**
     * The whole scale, for showing students how marks translate.
     *
     * @return array<string, string>  letter => range description
     */
    public static function legend(): array
    {
        $legend = [];
        $letters = array_keys(self::BANDS);

        foreach ($letters as $index => $letter) {
            $minimum = self::BANDS[$letter][0];
            // The band runs up to just under the next letter's minimum.
            $ceiling = $index === 0 ? 100 : self::BANDS[$letters[$index - 1]][0] - 1;

            $legend[$letter] = $index === array_key_last($letters)
                ? "0 - {$ceiling}%"
                : "{$minimum} - {$ceiling}%";
        }

        return $legend;
    }
}
