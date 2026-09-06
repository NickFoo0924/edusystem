<?php

/**
 * LearnSync -- Eloquent model
 *
 * Module 2: Academic Resources Repository
 *
 * @author Foo Chong Xian
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Module 2. Either an uploaded file or a link to an external resource; the
 * Adapter pattern is what lets one Blade view render both.
 */
class CourseMaterial extends Model
{
    use HasFactory;

    /**
     * The fixed sections every course page shows, in display order.
     *
     * Kept here rather than in a view so the upload form, the course page and
     * the validation rules all read the same list -- adding a category is one
     * edit, not four.
     */
    public const CATEGORIES = [
        'lecture' => 'Lecture notes',
        'tutorial' => 'Tutorial question',
        'practical' => 'Practical question',
        'other' => 'Others',
    ];

    /**
     * The heading this material files under.
     */
    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->type] ?? self::CATEGORIES['other'];
    }

    protected $fillable = [
        'course_id',
        'title',
        'type',
        'file_path',
        'is_external',
    ];

    protected function casts(): array
    {
        return [
            'is_external' => 'boolean',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
