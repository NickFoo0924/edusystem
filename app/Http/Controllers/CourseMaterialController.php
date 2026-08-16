<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseMaterial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * MODULE 2 -- uploading files and attaching external links.
 *
 * Both kinds land in the same table with is_external telling them apart; the
 * Adapter pattern takes it from there.
 */
class CourseMaterialController extends Controller
{
    public function create(Request $request, Course $course): View
    {
        $this->authoriseInstructor($request, $course);

        return view('materials.create', compact('course'));
    }

    public function store(Request $request, Course $course): RedirectResponse
    {
        $this->authoriseInstructor($request, $course);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:lecture,tutorial,practical'],
            'source' => ['required', 'in:file,link'],
            'file' => ['required_if:source,file', 'file', 'max:20480'],
            'url' => ['required_if:source,link', 'nullable', 'url', 'max:255'],
        ], [
            'file.required_if' => 'Choose a file to upload.',
            'url.required_if' => 'Enter the address of the external resource.',
        ]);

        $isExternal = $data['source'] === 'link';

        CourseMaterial::create([
            'course_id' => $course->id,
            'title' => $data['title'],
            'type' => $data['type'],
            'is_external' => $isExternal,
            // One column holds either a stored path or a URL. The adapters are
            // what make that difference invisible downstream.
            'file_path' => $isExternal
                ? $data['url']
                : $request->file('file')->store('materials/'.$course->id, 'public'),
        ]);

        return redirect()->route('courses.show', $course)->with('success', 'Material added.');
    }

    public function destroy(Request $request, Course $course, CourseMaterial $material): RedirectResponse
    {
        $this->authoriseInstructor($request, $course);
        abort_unless($material->course_id === $course->id, 404);

        // Only delete from disk when there is a real file behind it.
        if (! $material->is_external && Storage::disk('public')->exists($material->file_path)) {
            Storage::disk('public')->delete($material->file_path);
        }

        $material->delete();

        return redirect()->route('courses.show', $course)->with('success', 'Material removed.');
    }

    private function authoriseInstructor(Request $request, Course $course): void
    {
        abort_unless($request->user()->can('material.create'), 403);
        abort_unless($course->instructor_id === $request->user()->id, 403, 'This course belongs to another instructor.');
    }
}
