<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\CertificateTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * MODULE 1 (1C) -- certificate template management.
 *
 * Administrators own these; instructors cannot create them (Section 7). The
 * body text carries the placeholders the CredentialAuthority substitutes at
 * issuance.
 */
class CertificateTemplateController extends Controller
{
    /**
     * The placeholders 1C defines, offered to the admin as a reference.
     */
    public const PLACEHOLDERS = [
        '{{student_name}}' => "the holder's full name",
        '{{course_title}}' => 'the course, or the learning path for a pathway certificate',
        '{{score}}' => 'the final score, without the percent sign',
        '{{issued_date}}' => 'the date of issue, e.g. 16 August 2026',
        '{{credential_id}}' => 'the public credential ID',
    ];

    public function index(Request $request): View
    {
        abort_unless($request->user()->can('template.manage'), 403);

        return view('templates.index', [
            'templates' => CertificateTemplate::withCount('certificates')->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('template.manage'), 403);

        return view('templates.create', ['placeholders' => self::PLACEHOLDERS]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('template.manage'), 403);

        $data = $this->validated($request);
        $template = CertificateTemplate::create($data);

        ActivityLog::record('template.created', $template);

        return redirect()->route('templates.index')->with('success', "Template \"{$template->name}\" created.");
    }

    public function edit(Request $request, CertificateTemplate $template): View
    {
        abort_unless($request->user()->can('template.manage'), 403);

        return view('templates.edit', [
            'template' => $template,
            'placeholders' => self::PLACEHOLDERS,
        ]);
    }

    public function update(Request $request, CertificateTemplate $template): RedirectResponse
    {
        abort_unless($request->user()->can('template.manage'), 403);

        $template->update($this->validated($request));

        ActivityLog::record('template.updated', $template);

        return redirect()->route('templates.index')->with('success', 'Template updated.');
    }

    public function destroy(Request $request, CertificateTemplate $template): RedirectResponse
    {
        abort_unless($request->user()->can('template.manage'), 403);

        // Issued certificates keep a foreign key to their template, and their
        // PDFs were rendered from it. Deleting would orphan a permanent record.
        if ($template->certificates()->exists()) {
            return back()->with('error', 'Certificates have been issued from this template, so it cannot be deleted. Deactivate it instead.');
        }

        $name = $template->name;
        ActivityLog::record('template.deleted', $template);
        $template->delete();

        return redirect()->route('templates.index')->with('success', "Template \"{$name}\" deleted.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'body_text' => ['required', 'string', 'max:5000'],
            'signature' => ['nullable', 'image', 'max:2048'],
            'background' => ['nullable', 'image', 'max:4096'],
        ]);

        $attributes = [
            'name' => $data['name'],
            'body_text' => $data['body_text'],
            'is_active' => $request->boolean('is_active'),
        ];

        if ($request->hasFile('signature')) {
            $attributes['signature_path'] = $request->file('signature')->store('templates', 'public');
        }

        if ($request->hasFile('background')) {
            $attributes['background_path'] = $request->file('background')->store('templates', 'public');
        }

        return $attributes;
    }
}
