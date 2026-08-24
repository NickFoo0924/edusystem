{{-- templates/index.blade.php --}}
@extends('layout')

@section('title', 'Certificate templates')

@section('content')

<div class="flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight">Certificate templates</h1>
        <p class="mt-2 text-sm text-gray-500">
            Course certificates use the first active template; a learning path may name its own.
        </p>
    </div>
    <a href="{{ route('templates.create') }}"
       class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
        New template
    </a>
</div>

@include('partials.flash')

<div class="mt-8 space-y-4">
    @forelse ($templates as $template)
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <h2 class="font-semibold text-gray-900">{{ $template->name }}</h2>
                        @if ($template->is_active)
                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-emerald-800">Active</span>
                        @else
                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-gray-500">Inactive</span>
                        @endif
                    </div>
                    {{-- Sample values rather than the stored placeholders. The
                         template is a form with blanks; printing the blanks shows
                         field names to no purpose, when what a reader wants is what
                         the certificate will say. Truncated on a word so it never
                         breaks mid-term. --}}
                    <p class="mt-2 whitespace-pre-line text-xs text-gray-500">{{ Str::words($template->previewBody(), 45) }}</p>
                    <p class="mt-1 text-[11px] text-gray-400">Preview, using example details</p>
                    <p class="mt-2 text-xs text-gray-400">{{ $template->certificates_count }} {{ Str::plural('certificate', $template->certificates_count) }} issued from it</p>
                </div>

                <div class="flex items-center gap-3 text-sm">
                    <a href="{{ route('templates.edit', $template) }}"
                       class="font-medium text-blue-700 hover:text-blue-900">Edit</a>
                    <form method="post" action="{{ route('templates.destroy', $template) }}"
                          onsubmit="return confirm('Delete this template?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="font-medium text-red-700 hover:text-red-900">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    @empty
        <div class="rounded-xl border border-dashed border-gray-300 bg-white p-10 text-center">
            <p class="text-sm text-gray-500">No templates yet. Certificates cannot be issued without one.</p>
        </div>
    @endforelse
</div>

@endsection
