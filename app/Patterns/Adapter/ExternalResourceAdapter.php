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

/**
 * ADAPTEE WRAPPER for a link to somebody else's content.
 *
 * This is the adapter EduSystem.md Section 2 names explicitly: it makes a bare
 * external URL -- a YouTube video, a blog post, an online reference -- present
 * itself exactly like an uploaded file, so the resource list renders both
 * uniformly.
 */
class ExternalResourceAdapter implements DisplayableMaterial
{
    /**
     * Hosts worth naming specifically in the UI.
     */
    private const KNOWN_HOSTS = [
        'youtube.com' => 'YouTube',
        'youtu.be' => 'YouTube',
        'vimeo.com' => 'Vimeo',
        'github.com' => 'GitHub',
        'docs.google.com' => 'Google Docs',
        'drive.google.com' => 'Google Drive',
    ];

    public function __construct(private CourseMaterial $material)
    {
    }

    public function title(): string
    {
        return $this->material->title;
    }

    public function url(): string
    {
        return $this->material->file_path;
    }

    /**
     * Recognise the well-known hosts by name, and fall back to "Link".
     */
    public function kind(): string
    {
        $host = $this->host();

        foreach (self::KNOWN_HOSTS as $needle => $label) {
            if (str_contains($host, $needle)) {
                return $label;
            }
        }

        return 'Link';
    }

    public function detail(): string
    {
        return $this->host() !== '' ? $this->host() : 'external resource';
    }

    public function opensExternally(): bool
    {
        return true;
    }

    public function iconPath(): string
    {
        // Arrow leaving a box.
        return 'M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14';
    }

    private function host(): string
    {
        return str_replace('www.', '', (string) parse_url($this->material->file_path, PHP_URL_HOST));
    }
}
