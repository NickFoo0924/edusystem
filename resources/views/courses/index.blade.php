{{-- courses/index.blade.php --}}
@extends('layout')

@section('title', 'Courses')

@section('content')

<div class="flex items-center justify-between">
    <h1 class="text-2xl font-semibold tracking-tight">Courses</h1>
    @can('course.create')
        <a href="{{ route('courses.create') }}"
           class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
            New course
        </a>
    @endcan
</div>

@include('partials.flash')

@if ($teaching->isNotEmpty())
    <h2 class="mt-8 text-sm font-semibold uppercase tracking-wide text-gray-500">Courses you teach</h2>
    <div class="mt-3 grid grid-cols-1 gap-4 md:grid-cols-2">
        {{-- A div rather than an anchor, so the lecturer link below is not
             nested inside another link. --}}
        @foreach ($teaching as $course)
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm hover:border-blue-300">
                <h3 class="font-semibold">
                    <a href="{{ route('courses.show', $course) }}" class="text-gray-900 hover:text-blue-700">
                        <span class="mr-1.5 rounded bg-gray-100 px-1.5 py-0.5 font-mono text-[11px] font-medium text-gray-600">{{ $course->code }}</span>{{ $course->title }}
                    </a>
                </h3>
                <p class="mt-1 text-xs">
                    <a href="{{ route('instructors.show', $course->instructor) }}"
                       class="text-gray-500 underline decoration-gray-300 underline-offset-2 hover:text-blue-700">
                        {{ $course->instructor->name }}
                    </a>
                </p>
                <p class="mt-2 line-clamp-2 text-sm text-gray-600">{{ $course->description }}</p>
                <p class="mt-3 text-xs text-gray-500">
                    {{ $course->students_count }} enrolled &middot; {{ $course->materials_count }} materials
                </p>
            </div>
        @endforeach
    </div>
@endif

@if ($enrolled->isNotEmpty())
    <h2 class="mt-8 text-sm font-semibold uppercase tracking-wide text-gray-500">Your courses</h2>
    <div class="mt-3 grid grid-cols-1 gap-4 md:grid-cols-2">
        {{-- A div, not an anchor: the lecturer name inside is its own link, and
             an anchor nested inside an anchor is invalid HTML that browsers
             silently discard. The title carries the link to the course instead. --}}
        @foreach ($enrolled as $course)
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm hover:border-blue-300">
                <h3 class="font-semibold">
                    <a href="{{ route('courses.show', $course) }}" class="text-gray-900 hover:text-blue-700">
                        <span class="mr-1.5 rounded bg-gray-100 px-1.5 py-0.5 font-mono text-[11px] font-medium text-gray-600">{{ $course->code }}</span>{{ $course->title }}
                    </a>
                </h3>
                <p class="mt-1 text-xs">
                    <a href="{{ route('instructors.show', $course->instructor) }}"
                       class="text-gray-500 underline decoration-gray-300 underline-offset-2 hover:text-blue-700">
                        {{ $course->instructor->name }}
                    </a>
                </p>
                <p class="mt-2 line-clamp-2 text-sm text-gray-600">{{ $course->description }}</p>
            </div>
        @endforeach
    </div>
@endif

@can('course.enroll')
    <h2 class="mt-8 text-sm font-semibold uppercase tracking-wide text-gray-500">Available to enrol</h2>
    <div class="mt-3 space-y-3">
        @forelse ($available as $course)
            <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="min-w-0">
                    <h3 class="font-semibold text-gray-900"><span class="mr-1.5 rounded bg-gray-100 px-1.5 py-0.5 font-mono text-[11px] font-medium text-gray-600">{{ $course->code }}</span>{{ $course->title }}</h3>
                    <p class="text-xs"><a href="{{ route('instructors.show', $course->instructor) }}" class="text-gray-500 underline decoration-gray-300 underline-offset-2 hover:text-blue-700">{{ $course->instructor->name }}</a></p>
                    <p class="mt-1 line-clamp-2 text-sm text-gray-600">{{ $course->description }}</p>
                </div>
                <form method="post" action="{{ route('courses.enrol', $course) }}">
                    @csrf
                    <button type="submit"
                            class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
                        Enrol
                    </button>
                </form>
            </div>
        @empty
            <p class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-500">
                You are enrolled in every available course.
            </p>
        @endforelse
    </div>
@endcan

{{-- Administrators teach and enrol in nothing, so they get the full catalogue
     for oversight rather than an empty page. --}}
@if ($all->isNotEmpty())
    <h2 class="mt-8 text-sm font-semibold uppercase tracking-wide text-gray-500">
        All courses ({{ $all->count() }})
    </h2>
    <div class="mt-3 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-5 py-3">Code</th>
                    <th class="px-5 py-3">Course</th>
                    <th class="px-5 py-3">Lecturer</th>
                    <th class="px-5 py-3">Students</th>
                    <th class="px-5 py-3">Materials</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($all as $course)
                    <tr>
                        <td class="px-5 py-3">
                            <span class="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-xs font-medium text-gray-600">
                                {{ $course->code }}
                            </span>
                        </td>
                        <td class="px-5 py-3 font-medium text-gray-900">{{ $course->title }}</td>
                        <td class="px-5 py-3"><a href="{{ route('instructors.show', $course->instructor) }}" class="text-gray-500 underline decoration-gray-300 underline-offset-2 hover:text-blue-700">{{ $course->instructor->name }}</a></td>
                        <td class="px-5 py-3 text-gray-700">{{ $course->students_count }}</td>
                        <td class="px-5 py-3 text-gray-700">{{ $course->materials_count }}</td>
                        <td class="px-5 py-3 text-right">
                            <a href="{{ route('courses.show', $course) }}"
                               class="font-medium text-blue-700 hover:text-blue-900">Open</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

@if ($teaching->isEmpty() && $enrolled->isEmpty() && $all->isEmpty() && ! auth()->user()->can('course.enroll'))
    <div class="mt-8 rounded-xl border border-dashed border-gray-300 bg-white p-10 text-center">
        <p class="text-sm text-gray-500">You have no courses.</p>
    </div>
@endif

@endsection
