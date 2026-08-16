<?php

namespace App\Patterns\Adapter;

use App\Models\CourseMaterial;
use Illuminate\Support\Collection;

/**
 * Chooses the right adapter for a material.
 *
 * This is the single place in the system that inspects is_external. Everything
 * downstream -- controllers, views -- deals only in DisplayableMaterial, which
 * is what the Adapter pattern is buying us.
 *
 * Deliberately a plain static helper, not a second design pattern: Module 2's
 * one GoF pattern is the Adapter (EduSystem.md Section 2).
 */
class MaterialAdapterFactory
{
    public static function for(CourseMaterial $material): DisplayableMaterial
    {
        return $material->is_external
            ? new ExternalResourceAdapter($material)
            : new FileResourceAdapter($material);
    }

    /**
     * Wrap a whole collection, preserving the material alongside its adapter so
     * a view can still reach the underlying record for edit and delete links.
     *
     * @param  iterable<CourseMaterial>  $materials
     * @return Collection<int, array{material: CourseMaterial, display: DisplayableMaterial}>
     */
    public static function forAll(iterable $materials): Collection
    {
        return collect($materials)->map(fn (CourseMaterial $material) => [
            'material' => $material,
            'display' => self::for($material),
        ]);
    }
}
