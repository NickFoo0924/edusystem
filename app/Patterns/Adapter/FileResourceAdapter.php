<?php

/**
 * LearnSync -- Adapter pattern (structural)
 *
 * Module 2: Academic Resources Repository
 *
 * @author Foo Chong Xian
 */

namespace App\Patterns\Adapter;

use App\Models\CourseMaterial;
use Illuminate\Support\Facades\Storage;

/**
 * ADAPTEE WRAPPER for an uploaded file.
 *
 * Adapts a stored file -- a path on the public disk -- to the
 * DisplayableMaterial interface the views speak.
 */
class FileResourceAdapter implements DisplayableMaterial
{
    public function __construct(private CourseMaterial $material)
    {
    }

    public function title(): string
    {
        return $this->material->title;
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->material->file_path);
    }

    public function kind(): string
    {
        $extension = strtoupper(pathinfo($this->material->file_path, PATHINFO_EXTENSION));

        return $extension !== '' ? $extension : 'File';
    }

    /**
     * The file size, read from disk and rendered in human units.
     */
    public function detail(): string
    {
        if (! Storage::disk('public')->exists($this->material->file_path)) {
            return 'file missing';
        }

        $bytes = Storage::disk('public')->size($this->material->file_path);

        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($bytes < 1024) {
                return round($bytes, $unit === 'B' ? 0 : 1).' '.$unit;
            }
            $bytes /= 1024;
        }

        return round($bytes, 1).' TB';
    }

    public function opensExternally(): bool
    {
        return false;
    }

    public function iconPath(): string
    {
        // Document icon.
        return 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z';
    }
}
