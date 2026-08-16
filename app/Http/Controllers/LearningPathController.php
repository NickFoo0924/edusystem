<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\CertificateTemplate;
use App\Models\Course;
use App\Models\LearningPath;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * BUILD PRIORITY ITEM 6 -- administrator management of learning paths
 * (EduSystem.md 1C).
 *
 * A learning path is an ordered collection of courses, e.g. "Web Development
 * Pathway" = HTML -> PHP -> Laravel. Completing every course in it earns a
 * higher-tier pathway certificate on top of the individual course ones, which
 * the CredentialAuthority mints automatically.
 *
 * Module 1 only reads `courses` here -- Module 2 remains their sole writer
 * (EduSystem.md Section 2A).
 *
 * Guarded by can:learningpath.manage route middleware.
 */
class LearningPathController extends Controller
{
    public function index(): View
    {
        $paths = LearningPath::with('courses')
            ->withCount('certificates')
            ->orderBy('title')
            ->get();

        return view('learning_paths.index', compact('paths'));
    }

    public function create(): View
    {
        return view('learning_paths.create', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $path = LearningPath::create($data['attributes']);
        $path->courses()->sync($data['courses']);

        ActivityLog::record('learningpath.created', $path);

        return redirect()->route('learning-paths.index')
            ->with('success', "Learning path \"{$path->title}\" created.");
    }

    public function edit(LearningPath $learningPath): View
    {
        $learningPath->load('courses');

        return view('learning_paths.edit', $this->formData() + ['path' => $learningPath]);
    }

    public function update(Request $request, LearningPath $learningPath): RedirectResponse
    {
        $data = $this->validated($request);

        $learningPath->update($data['attributes']);
        $learningPath->courses()->sync($data['courses']);

        ActivityLog::record('learningpath.updated', $learningPath);

        return redirect()->route('learning-paths.index')
            ->with('success', "Learning path \"{$learningPath->title}\" updated.");
    }

    public function destroy(LearningPath $learningPath): RedirectResponse
    {
        // Certificates reference the path with nullOnDelete, so deleting it
        // would orphan any pathway credential already issued from it. Those are
        // permanent records, so the path is kept and can only be deactivated.
        if ($learningPath->certificates()->exists()) {
            return redirect()->route('learning-paths.index')
                ->with('error', "\"{$learningPath->title}\" has issued certificates and cannot be deleted. Deactivate it instead.");
        }

        $title = $learningPath->title;
        ActivityLog::record('learningpath.deleted', $learningPath);
        $learningPath->delete();

        return redirect()->route('learning-paths.index')
            ->with('success', "Learning path \"{$title}\" deleted.");
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'courses' => Course::with('instructor')->orderBy('title')->get(),
            'templates' => CertificateTemplate::where('is_active', true)->orderBy('name')->get(),
        ];
    }

    /**
     * Validate the form and turn the course rows into a sync payload.
     *
     * The form posts a checkbox plus a sequence number per course. Only ticked
     * courses are kept, and they are renumbered from 1 in the order given so a
     * path can never end up with duplicate or gapped sequence values.
     *
     * @return array{attributes: array<string, mixed>, courses: array<int, array<string, int>>}
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'certificate_template_id' => ['nullable', 'exists:certificate_templates,id'],
            'course_ids' => ['required', 'array', 'min:1'],
            'course_ids.*' => ['exists:courses,id'],
            'sequence' => ['array'],
            'sequence.*' => ['nullable', 'integer', 'min:1'],
        ], [
            'course_ids.required' => 'A learning path needs at least one course.',
        ]);

        $selected = $validated['course_ids'];
        $sequences = $validated['sequence'] ?? [];

        // Sort by the number the administrator typed, then renumber 1..n.
        usort($selected, fn ($a, $b) => ($sequences[$a] ?? PHP_INT_MAX) <=> ($sequences[$b] ?? PHP_INT_MAX));

        $sync = [];
        foreach ($selected as $index => $courseId) {
            $sync[$courseId] = ['sequence' => $index + 1];
        }

        return [
            'attributes' => [
                'title' => $validated['title'],
                'description' => $validated['description'],
                'certificate_template_id' => $validated['certificate_template_id'] ?? null,
                'is_active' => $request->boolean('is_active'),
            ],
            'courses' => $sync,
        ];
    }
}
