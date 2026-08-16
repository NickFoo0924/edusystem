{{-- dashboard/instructor.blade.php --}}
@extends('layout')

@section('title', 'Dashboard')

@section('content')

<div class="flex flex-wrap items-center justify-between gap-3">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight">Hello, {{ auth()->user()->name }}</h1>
        <p class="mt-2 text-sm text-gray-500">Your courses and the work waiting on you.</p>
    </div>
    @can('progress.view_students')
        <a href="{{ route('analytics.index') }}"
           class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
            Class analytics
        </a>
    @endcan
</div>

@include('partials.flash')

<section class="mt-8 overflow-hidden rounded-xl border {{ $awaitingReview->isEmpty() ? 'border-gray-200' : 'border-amber-300' }} bg-white shadow-sm">
    <div class="border-b border-gray-200 bg-gray-50 px-6 py-3">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-600">
            Awaiting your review ({{ $awaitingReview->count() }})
        </h2>
    </div>
    <ul class="divide-y divide-gray-100">
        @forelse ($awaitingReview as $submission)
            <li class="flex items-center justify-between px-6 py-4">
                <div>
                    <p class="font-medium text-gray-900">{{ $submission->student->name }}</p>
                    <p class="text-xs text-gray-500">
                        {{ $submission->assignment->title }} &middot; {{ $submission->assignment->course->title }}
                        @if ($submission->submitted_at)
                            &middot; handed in {{ $submission->submitted_at->diffForHumans() }}
                        @endif
                    </p>
                </div>
                <a href="{{ route('assignments.show', $submission->assignment) }}"
                   class="text-sm font-medium text-blue-700 hover:text-blue-900">Mark</a>
            </li>
        @empty
            <li class="px-6 py-10 text-center text-sm text-gray-500">Nothing waiting. All caught up.</li>
        @endforelse
    </ul>
</section>

<h2 class="mt-8 text-sm font-semibold uppercase tracking-wide text-gray-500">Your courses</h2>
<div class="mt-3 grid grid-cols-1 gap-4 md:grid-cols-2">
    @forelse ($courses as $course)
        <a href="{{ route('courses.show', $course) }}"
           class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm hover:border-blue-300">
            <h3 class="font-semibold text-gray-900"><span class="mr-1.5 rounded bg-gray-100 px-1.5 py-0.5 font-mono text-[11px] font-medium text-gray-600">{{ $course->code }}</span>{{ $course->title }}</h3>
            <p class="mt-2 text-xs text-gray-500">
                {{ $course->students_count }} students &middot; {{ $course->materials_count }} materials &middot;
                {{ $course->quizzes_count }} quizzes &middot; {{ $course->assignments_count }} assignments
            </p>
        </a>
    @empty
        <p class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-500 md:col-span-2">
            You have no courses yet.
            <a href="{{ route('courses.create') }}" class="font-medium text-blue-700">Create one</a>.
        </p>
    @endforelse
</div>

@endsection
