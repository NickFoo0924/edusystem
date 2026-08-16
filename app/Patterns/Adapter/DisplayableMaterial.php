<?php

namespace App\Patterns\Adapter;

/**
 * MODULE 2 DESIGN PATTERN -- ADAPTER (Structural). The TARGET interface.
 *
 * An instructor may attach either an uploaded PDF or a link to something
 * outside the system, such as a YouTube video. The two have nothing in common:
 * one is a file on disk with a size and a MIME type, the other is a URL on
 * somebody else's server.
 *
 * Every material is wrapped in an adapter exposing this one interface, so the
 * Blade view iterates a single list and calls the same methods on each,
 * with no is_external branching in the template (EduSystem.md Section 2).
 */
interface DisplayableMaterial
{
    /**
     * What the student sees as the material's name.
     */
    public function title(): string;

    /**
     * Where clicking it takes them.
     */
    public function url(): string;

    /**
     * Short human label for the kind of resource, e.g. "PDF" or "YouTube".
     */
    public function kind(): string;

    /**
     * Extra detail for the list: a file size, or the external host.
     */
    public function detail(): string;

    /**
     * Should the link open in a new tab? True for anything off-site.
     */
    public function opensExternally(): bool;

    /**
     * Heroicon-style path data for the list icon, so the view stays free of
     * per-type conditionals.
     */
    public function iconPath(): string;
}
