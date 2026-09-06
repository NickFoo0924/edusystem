{{--
    LearnSync -- Blade view
    Module 5: Academic Progress Analytics
    @author Ong Kwong Wei
--}}
{{-- assignments/show.blade.php --}}
@extends('layout')

@section('title', $assignment->title)

@section('content')

<a href="{{ route('courses.show', $assignment->course) }}" class="text-sm text-gray-500 hover:text-gray-800">
    &larr; Back to {{ $assignment->course->title }}
</a>

<div class="mt-6 flex flex-wrap items-start justify-between gap-4">
    <div class="min-w-0">
        <h1 class="text-2xl font-semibold tracking-tight">{{ $assignment->title }}</h1>
        <p class="mt-1 text-sm {{ $assignment->isOverdue() ? 'text-red-600' : 'text-gray-500' }}">
            Due {{ $assignment->due_date->format('j F Y, H:i') }}
            @if ($assignment->isOverdue()) &middot; deadline passed @endif
        </p>

        {{-- The late policy, stated plainly to whoever is looking. --}}
        <p class="mt-2 inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs
            {{ $assignment->isClosed()
                ? 'bg-red-50 text-red-800'
                : ($assignment->allow_late_submission ? 'bg-gray-100 text-gray-700' : 'bg-amber-50 text-amber-900') }}">
            @if ($assignment->isClosed())
                Closed &mdash; this assignment stopped accepting work at its deadline
            @else
                {{ $assignment->latePolicyLabel() }}
            @endif
        </p>

        @if ($assignment->description)
            <p class="mt-3 max-w-2xl whitespace-pre-line text-sm text-gray-600">{{ $assignment->description }}</p>
        @endif
    </div>

    @if ($isOwner)
        <div class="flex items-center gap-2">
            <a href="{{ route('assignments.edit', $assignment) }}"
               class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Edit
            </a>
            <form method="post" action="{{ route('assignments.destroy', $assignment) }}"
                  onsubmit="return confirm('Delete this assignment and every submission to it?');">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50">
                    Delete
                </button>
            </form>
        </div>
    @endif
</div>

@include('partials.flash')

@if ($isOwner)

    {{-- INSTRUCTOR: the review queue. --}}
    <div class="mt-8 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 bg-gray-50 px-6 py-3">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-600">
                Submissions ({{ $submissions->count() }})
            </h2>
            @php $lateCount = $submissions->filter(fn ($s) => $s->submitted_at && ! $s->wasOnTime())->count(); @endphp
            @if ($lateCount > 0)
                <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-900">
                    {{ $lateCount }} turned in late
                </span>
            @endif
        </div>
        <ul class="divide-y divide-gray-100">
            @forelse ($submissions as $submission)
                <li class="px-6 py-4">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-medium text-gray-900">{{ $submission->student->name }}</p>
                                @if ($submission->submitted_at && ! $submission->wasOnTime())
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-amber-900">
                                        Turned in late
                                    </span>
                                @elseif ($submission->submitted_at)
                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-emerald-800">
                                        On time
                                    </span>
                                @endif
                            </div>
                            <p class="mt-0.5 text-xs text-gray-500">
                                <span class="capitalize">{{ $submission->state }}</span>
                                @if ($submission->submitted_at)
                                    &middot; handed in {{ $submission->submitted_at->format('j M, H:i') }}
                                    @unless ($submission->wasOnTime())
                                        <span class="text-amber-700">
                                            ({{ $submission->submitted_at->diffForHumans($assignment->due_date, ['syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE]) }} after the deadline)
                                        </span>
                                    @endunless
                                @endif
                            </p>
                        </div>

                        <div class="flex items-center gap-3">
                            @if ($submission->file_path)
                                <a href="{{ route('submissions.download', $submission) }}"
                                   class="text-sm font-medium text-blue-700 hover:text-blue-900">Download</a>
                            @endif

                            @if ($submission->grade)
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-sm font-medium text-gray-800">
                                    {{ rtrim(rtrim(number_format($submission->grade->calculated_score, 2), '0'), '.') }}%
                                    <span class="ml-1.5 rounded px-1.5 py-0.5 text-xs font-bold {{ \App\Support\GradeScale::classesFor($submission->grade->calculated_score) }}">{{ $submission->grade->letter() }}</span>
                                </span>
                            @elseif ($submission->state()->canAssignGrade())
                                <form method="post" action="{{ route('submissions.grade', $submission) }}"
                                      class="flex items-center gap-2">
                                    @csrf
                                    <input type="number" name="calculated_score" step="0.01" min="0" max="100" required
                                           placeholder="Score"
                                           class="w-24 rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <button type="submit"
                                            class="rounded-lg bg-blue-700 px-3 py-2 text-sm font-medium text-white hover:bg-blue-800">
                                        Grade
                                    </button>
                                </form>
                            @else
                                <span class="text-xs text-gray-400">Not submitted yet</span>
                            @endif
                        </div>
                    </div>
                </li>
            @empty
                <li class="px-6 py-10 text-center text-sm text-gray-500">Nothing submitted yet.</li>
            @endforelse
        </ul>
    </div>

@else

    {{-- STUDENT: their own submission, driven by the state object and the policy. --}}
    @php $state = $mine?->state(); @endphp

    <div class="mt-8 max-w-xl rounded-xl border border-gray-200 bg-white p-8 shadow-sm">

        @if ($mine)
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs uppercase tracking-wide text-gray-500">Status</p>
                    <div class="mt-1 flex items-center gap-2">
                        <p class="text-lg font-semibold capitalize text-gray-900">{{ $mine->state }}</p>
                        @if ($mine->submitted_at && ! $mine->wasOnTime())
                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-amber-900">
                                Turned in late
                            </span>
                        @endif
                    </div>
                </div>
                @if ($mine->grade)
                    <div class="text-right">
                        <p class="text-xs uppercase tracking-wide text-gray-500">Grade</p>
                        <p class="mt-1 flex items-baseline justify-end gap-1.5">
                            <span class="text-lg font-semibold text-gray-900">{{ rtrim(rtrim(number_format($mine->grade->calculated_score, 2), '0'), '.') }}%</span>
                            <span class="rounded px-1.5 py-0.5 text-xs font-bold {{ \App\Support\GradeScale::classesFor($mine->grade->calculated_score) }}">{{ $mine->grade->letter() }}</span>
                        </p>
                    </div>
                @endif
            </div>

            @if ($mine->file_path)
                <p class="mt-4 text-sm text-gray-600">
                    Current file:
                    <a href="{{ route('submissions.download', $mine) }}" class="font-medium text-blue-700 hover:text-blue-900">
                        download
                    </a>
                </p>
            @endif
        @else
            <p class="text-sm text-gray-600">You have not started this assignment yet.</p>
        @endif

        @if ($assignment->isClosed())

            {{-- Policy is "close at deadline" and the deadline has gone. --}}
            <div class="mt-6 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800">
                <p class="font-medium">This assignment is closed.</p>
                <p class="mt-1 text-xs">
                    The deadline was {{ $assignment->due_date->format('j F Y, H:i') }} and your instructor chose not
                    to accept late work.
                    @if ($mine && $mine->state === 'draft')
                        Your draft can no longer be submitted &mdash; speak to your instructor.
                    @endif
                </p>
            </div>

        @else

            {{-- A warning before they act, not after. --}}
            @if ($assignment->wouldBeLate() && (! $mine || $mine->state === 'draft'))
                <div class="mt-6 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    The deadline passed {{ $assignment->due_date->diffForHumans() }}. You can still submit, but it
                    will be recorded as <span class="font-medium">turned in late</span>.
                </div>
            @endif

            @if ($state === null || $state->canUpdateFile())
                <form method="post" action="{{ route('assignments.submissions.store', $assignment) }}"
                      enctype="multipart/form-data" class="mt-6 border-t border-gray-100 pt-6">
                    @csrf
                    <label for="file" class="block text-sm font-medium text-gray-700">
                        {{ $mine?->file_path ? 'Replace file' : 'Upload your work' }}
                    </label>
                    <input id="file" name="file" type="file" required
                           class="mt-1 block w-full text-sm text-gray-600 file:mr-4 file:rounded-lg file:border-0 file:bg-gray-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-gray-700 hover:file:bg-gray-200">
                    <button type="submit"
                            class="mt-3 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Save draft
                    </button>
                </form>
            @endif

            @if ($state !== null && $state->canSubmit() && $mine->file_path)
                <form method="post" action="{{ route('submissions.submit', $mine) }}" class="mt-4"
                      onsubmit="return confirm('{{ $assignment->wouldBeLate()
                          ? 'This will be recorded as turned in late. Submit anyway?'
                          : 'Submit for marking? You will not be able to change the file afterwards.' }}');">
                    @csrf
                    <button type="submit"
                            class="w-full rounded-lg px-4 py-2.5 text-sm font-medium text-white
                                {{ $assignment->wouldBeLate() ? 'bg-amber-600 hover:bg-amber-700' : 'bg-blue-700 hover:bg-blue-800' }}">
                        {{ $assignment->wouldBeLate() ? 'Submit late for marking' : 'Submit for marking' }}
                    </button>
                </form>
            @endif

            @if ($state !== null && ! $state->canUpdateFile())
                <p class="mt-6 rounded-lg bg-gray-50 px-4 py-3 text-xs text-gray-500">
                    This submission is {{ $state->label() }}, so the file is locked.
                </p>
            @endif

        @endif

    </div>

@endif

@endsection
