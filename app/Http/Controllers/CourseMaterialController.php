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
    /**
     * File types a lecturer may upload as course material.
     *
     * An allow-list, never a block-list. A block-list has to anticipate every
     * dangerous extension, and it only takes one that was forgotten (.phtml,
     * .phar, .htaccess) for the defence to fail. An allow-list fails the other
     * way: something new is refused until it is deliberately added.
     */
    private const ALLOWED_UPLOAD_TYPES = [
        'pdf',
        'doc', 'docx',
        'ppt', 'pptx',
        'xls', 'xlsx', 'csv',
        'txt', 'md',
        'png', 'jpg', 'jpeg', 'gif', 'webp',
        'zip',
    ];

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
            'type' => ['required', 'in:'.implode(',', array_keys(CourseMaterial::CATEGORIES))],
            'source' => ['required', 'in:file,link'],
            /*
             * SECURITY (Module 2): the allow-list is the whole defence here.
             *
             * An uploaded material is written to the `public` disk, which is
             * symlinked into the web root so a student can open it directly.
             * Anything landing there is reachable at a URL, and under Apache a
             * file ending .php at a reachable URL is EXECUTED rather than
             * downloaded. Without this rule a lecturer account could upload
             * shell.php and run arbitrary code on the server.
             *
             * `mimes:` checks the extension against the file's real MIME type,
             * so renaming shell.php to shell.pdf does not get past it either.
             * The list is teaching formats only: documents, slides,
             * spreadsheets, images and archives. Nothing on it is executable.
             */
            'file' => [
                'required_if:source,file',
                'file',
                'max:20480',
                'mimes:'.implode(',', self::ALLOWED_UPLOAD_TYPES),
            ],
            'url' => [
                'required_if:source,link',
                'nullable',
                'url',
                'max:255',
                // SECURITY (Module 2): only http(s). A javascript: or data:
                // URL saved here would become a scripted link rendered as an
                // ordinary course material for every student on the course.
                'starts_with:http://,https://',
            ],
        ], [
            'file.required_if' => 'Choose a file to upload.',
            'file.mimes' => 'That file type is not allowed. Upload a document, slide deck, spreadsheet, image or zip archive.',
            'url.required_if' => 'Enter the address of the external resource.',
            'url.starts_with' => 'The address must begin with http:// or https://.',
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
