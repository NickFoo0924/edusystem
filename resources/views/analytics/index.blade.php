{{-- analytics/index.blade.php -- Module 5 --}}
@extends('layout')

@section('title', 'Class analytics')

@section('content')

<h1 class="text-2xl font-semibold tracking-tight">Class analytics</h1>
<p class="mt-2 max-w-2xl text-sm text-gray-500">
    Cohort performance across quizzes and coursework. A student's own progress towards their next
    certificate lives on their dashboard.
</p>

@include('partials.flash')

<div class="mt-8 space-y-6">

    @forelse ($courses as $stats)
        @php $course = $stats['course']; @endphp
        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">

            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 bg-gray-50 px-6 py-3">
                <div>
                    <h2 class="font-semibold text-gray-900"><span class="mr-1.5 rounded bg-gray-100 px-1.5 py-0.5 font-mono text-[11px] font-medium text-gray-600">{{ $course->code }}</span>{{ $course->title }}</h2>
                    <p class="text-xs text-gray-500">
                        <a href="{{ route('instructors.show', $course->instructor) }}" class="text-gray-500 underline decoration-gray-300 underline-offset-2 hover:text-blue-700">{{ $course->instructor->name }}</a> &middot; {{ $course->students_count }} enrolled
                    </p>
                </div>
                <a href="{{ route('courses.show', $course) }}"
                   class="text-sm font-medium text-blue-700 hover:text-blue-900">Open course</a>
            </div>

            <div class="grid grid-cols-2 gap-px bg-gray-100 md:grid-cols-4">
                @foreach ([
                    ['Class average', $stats['average'], $stats['averageLetter']],
                    ['Highest', $stats['highest'], $stats['highestLetter']],
                    ['Lowest', $stats['lowest'], $stats['lowestLetter']],
                ] as [$label, $value, $letter])
                    <div class="bg-white px-6 py-4">
                        <p class="text-xs uppercase tracking-wide text-gray-500">{{ $label }}</p>
                        <p class="mt-1 flex items-baseline gap-2">
                            <span class="text-xl font-semibold text-gray-900">
                                {{ $value !== null ? $value.'%' : '—' }}
                            </span>
                            @if ($letter !== null)
                                <span class="rounded px-1.5 py-0.5 text-xs font-bold {{ \App\Support\GradeScale::classesFor($value) }}">
                                    {{ $letter }}
                                </span>
                            @endif
                        </p>
                    </div>
                @endforeach
                <div class="bg-white px-6 py-4">
                    <p class="text-xs uppercase tracking-wide text-gray-500">Passed</p>
                    <p class="mt-1 text-xl font-semibold text-gray-900">
                        {{ $stats['passed'] }} <span class="text-sm font-normal text-gray-400">/ {{ $stats['graded'] }}</span>
                    </p>
                    <p class="text-[11px] text-gray-400">D and above</p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 px-6 py-5 lg:grid-cols-2">

                {{-- Grade distribution, drawn with plain divs so it needs no JS. --}}
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Grade distribution</h3>
                    @php
                        $peak = max(1, max($stats['distribution']));
                        // A representative mark per family, purely to pick the bar colour.
                        $swatch = ['A' => 85, 'B' => 67, 'C' => 52, 'D' => 41, 'F' => 20];
                        $bars = ['A' => 'bg-emerald-500', 'B' => 'bg-blue-600', 'C' => 'bg-amber-500', 'D' => 'bg-orange-500', 'F' => 'bg-red-600'];
                    @endphp
                    <div class="mt-3 space-y-2">
                        @foreach ($stats['distribution'] as $letter => $count)
                            <div class="flex items-center gap-3">
                                <span class="w-8 shrink-0 rounded px-1.5 py-0.5 text-center text-xs font-bold
                                    {{ \App\Support\GradeScale::classesFor($swatch[$letter]) }}">
                                    {{ $letter }}
                                </span>
                                <div class="h-4 flex-1 overflow-hidden rounded bg-gray-100">
                                    <div class="h-full rounded {{ $bars[$letter] }}"
                                         style="width: {{ ($count / $peak) * 100 }}%"></div>
                                </div>
                                <span class="w-6 shrink-0 text-right text-xs text-gray-600">{{ $count }}</span>
                            </div>
                        @endforeach
                    </div>
                    <p class="mt-2 text-[11px] text-gray-400">
                        A&nbsp;80+ &middot; B&nbsp;65-79 &middot; C&nbsp;50-64 &middot; D&nbsp;40-49 &middot; F&nbsp;below&nbsp;40
                    </p>
                </div>

                {{-- Submission turnaround. --}}
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Submissions</h3>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Handed in</dt>
                            <dd class="font-medium text-gray-900">{{ $stats['submitted'] }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">On time</dt>
                            <dd class="font-medium text-emerald-700">{{ $stats['onTime'] }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Awaiting marking</dt>
                            <dd class="font-medium {{ $stats['awaiting'] > 0 ? 'text-amber-700' : 'text-gray-900' }}">
                                {{ $stats['awaiting'] }}
                            </dd>
                        </div>
                        <div class="flex justify-between border-t border-gray-100 pt-2">
                            <dt class="text-gray-500">Average turnaround</dt>
                            <dd class="font-medium text-gray-900">
                                {{ $stats['turnaround'] !== null ? $stats['turnaround'].' h' : 'nothing marked yet' }}
                            </dd>
                        </div>
                    </dl>
                </div>

            </div>
        </section>
    @empty
        <div class="rounded-xl border border-dashed border-gray-300 bg-white p-10 text-center">
            <p class="text-sm text-gray-500">No courses to report on.</p>
        </div>
    @endforelse

</div>

@endsection
