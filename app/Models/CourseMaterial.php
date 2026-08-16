<?php

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
