{{-- dashboard/student.blade.php --}}
@extends('layout')

@section('title', 'Dashboard')

@section('content')

<h1 class="text-2xl font-semibold tracking-tight">Hello, {{ auth()->user()->name }}</h1>
<p class="mt-2 text-sm text-gray-500">Your progress towards your next certificate.</p>

@include('partials.flash')

{{--
    DUE SOON -- placed first, before progress and credentials, because a
    deadline the student has not acted on matters more than anything else on
    this page. Overdue work sits at the top of the list.
--}}
@php
    $overdueCount = $outstanding->where('overdue', true)->count();
@endphp

<section class="mt-8 overflow-hidden rounded-xl border shadow-sm
    {{ $overdueCount > 0 ? 'border-red-300' : ($outstanding->isNotEmpty() ? 'border-amber-300' : 'border-gray-200') }}">

    <div class="flex flex-wrap items-center justify-between gap-2 border-b px-6 py-3
        {{ $overdueCount > 0 ? 'border-red-200 bg-red-50' : ($outstanding->isNotEmpty() ? 'border-amber-200 bg-amber-50' : 'border-gray-200 bg-gray-50') }}">
        <h2 class="text-sm font-semibold uppercase tracking-wide
            {{ $overdueCount > 0 ? 'text-red-800' : ($outstanding->isNotEmpty() ? 'text-amber-900' : 'text-gray-600') }}">
            Due soon
        </h2>
        <span class="text-xs {{ $overdueCount > 0 ? 'text-red-700' : 'text-gray-500' }}">
            @if ($overdueCount > 0)
                {{ $overdueCount }} overdue &middot;
            @endif
            {{ $outstanding->count() }} outstanding in the next 7 days
        </span>
        <x-list-toggle for="due-soon" :total="$outstanding->count()" />
    </div>

    <x-expandable-list id="due-soon" class="bg-white">
        @forelse ($outstanding as $item)
            @php
                $assignment = $item['assignment'];
                $tone = $item['overdue'] ? 'red' : ($item['dueToday'] ? 'amber' : 'gray');
            @endphp
            <li class="flex flex-wrap items-center justify-between gap-3 px-6 py-4">

                <div class="flex min-w-0 items-start gap-3">
                    {{-- A dot rather than an icon font, so urgency reads at a glance. --}}
                    <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full
                        {{ $tone === 'red' ? 'bg-red-600' : ($tone === 'amber' ? 'bg-amber-500' : 'bg-gray-300') }}"></span>

                    <div class="min-w-0">
                        <p class="font-medium text-gray-900">{{ $assignment->title }}</p>
                        <p class="mt-0.5 text-xs text-gray-500">
                            {{ $assignment->course->title }}
                            &middot;
                            <span class="{{ $tone === 'red' ? 'font-medium text-red-700' : ($tone === 'amber' ? 'font-medium text-amber-700' : '') }}">
                                @if ($item['overdue'])
                                    Overdue &mdash; was due {{ $assignment->due_date->diffForHumans() }}
                                @elseif ($item['dueToday'])
                                    Due today at {{ $assignment->due_date->format('H:i') }}
                                @else
                                    Due {{ $assignment->due_date->format('l j M') }} at {{ $assignment->due_date->format('H:i') }}
                                    ({{ $assignment->due_date->diffForHumans() }})
                                @endif
                            </span>
                        </p>

                        @if ($item['hasDraft'])
                            {{-- The trap this panel exists to catch. --}}
                            <p class="mt-1.5 inline-flex items-center gap-1 rounded bg-amber-100 px-2 py-0.5 text-[11px] font-medium text-amber-900">
                                Draft saved but not submitted
                            </p>
                        @endif
                    </div>
                </div>

                <a href="{{ route('assignments.show', $assignment) }}"
                   class="shrink-0 rounded-lg px-3 py-1.5 text-sm font-medium text-white
                       {{ $tone === 'red' ? 'bg-red-700 hover:bg-red-800' : 'bg-blue-700 hover:bg-blue-800' }}">
                    {{ $item['hasDraft'] ? 'Finish and submit' : 'Open' }}
                </a>
            </li>
        @empty
            <li class="px-6 py-8 text-center">
                <p class="text-sm text-gray-600">Nothing due in the next 7 days.</p>
                <p class="mt-1 text-xs text-gray-400">You are up to date on everything with a deadline.</p>
            </li>
        @endforelse
    </x-expandable-list>
</section>

<div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
    @foreach ([
        ['Courses', $courseCount, 'route' => 'courses.index'],
        ['Certificates', $certificates->count(), 'route' => 'certificates.index'],
        ['Badges', $badgeCount, 'route' => 'badges.cabinet'],
        ['Unread', $unread, 'route' => 'notifications.index'],
    ] as [$label, $value])
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p class="text-xs uppercase tracking-wide text-gray-500">{{ $label }}</p>
            <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $value }}</p>
        </div>
    @endforeach
</div>

{{-- Progress towards the certificate threshold, per course. --}}
<section class="mt-8 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
    <div class="flex items-center justify-between gap-4 border-b border-gray-200 bg-gray-50 px-6 py-3">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-600">
            Progress towards certification ({{ rtrim(rtrim(number_format($threshold, 1), '0'), '.') }}% needed)
        </h2>
        <x-list-toggle for="course-progress" :total="$progress->count()" />
    </div>
    <x-expandable-list id="course-progress">
        @forelse ($progress as $row)
            @php $met = $row->completion_percentage >= $threshold; @endphp
            <li class="px-6 py-4">
                <div class="flex items-center justify-between gap-4">
                    <p class="font-medium text-gray-900">{{ $row->course->title }}</p>
                    <span class="text-sm font-semibold {{ $met ? 'text-emerald-700' : 'text-gray-700' }}">
                        {{ rtrim(rtrim(number_format($row->completion_percentage, 2), '0'), '.') }}%
                    </span>
                </div>
                <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-gray-100">
                    <div class="h-full rounded-full {{ $met ? 'bg-emerald-500' : 'bg-blue-600' }}"
                         style="width: {{ min(100, $row->completion_percentage) }}%"></div>
                </div>
                <p class="mt-2 text-xs text-gray-500">
                    {{ $row->quizzes_passed }} quizzes passed &middot;
                    {{ $row->assignments_submitted }} assignments submitted
                    @if ($met) &middot; <span class="text-emerald-700">certificate earned</span> @endif
                </p>
            </li>
        @empty
            <li class="px-6 py-10 text-center text-sm text-gray-500">
                No progress recorded yet. Take a quiz or submit an assignment to get started.
            </li>
        @endforelse
    </x-expandable-list>
</section>

{{-- EduSystem.md 1B: the progress-over-time line chart, drawn from
     progress_snapshots. Chart.js is loaded from the compiled bundle. --}}
@if ($chart->isNotEmpty())
    <section class="mt-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-600">Progress over time</h2>
        <div class="mt-4">
            <canvas id="progressChart" height="110"></canvas>
        </div>
    </section>

    @push('scripts')
        <script>
            window.LEARNSYNC_PROGRESS = @json($chart);
        </script>
    @endpush
@endif

@if ($certificates->isNotEmpty())
    <section class="mt-6 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-6 py-3">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-600">Your credentials</h2>
            <x-list-toggle for="credentials" :total="$certificates->count()" />
        </div>
        <x-expandable-list id="credentials">
            @foreach ($certificates as $certificate)
                <li class="flex items-center justify-between px-6 py-4">
                    <div>
                        <p class="font-medium text-gray-900">
                            {{ $certificate->course?->title ?? $certificate->learningPath?->title }}
                        </p>
                        <p class="font-mono text-xs text-gray-500">{{ $certificate->credential_id }}</p>
                    </div>
                    <a href="{{ route('certificates.show', $certificate) }}"
                       class="text-sm font-medium text-blue-700 hover:text-blue-900">View</a>
                </li>
            @endforeach
        </x-expandable-list>
    </section>
@endif

@endsection
