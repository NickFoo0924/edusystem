{{-- courses/show.blade.php --}}
@extends('layout')

@section('title', $course->title)

@section('content')

<a href="{{ route('courses.index') }}" class="text-sm text-gray-500 hover:text-gray-800">&larr; All courses</a>

<div class="mt-6 flex flex-wrap items-start justify-between gap-4">
    <div class="min-w-0">
        <h1 class="text-2xl font-semibold tracking-tight">
            <span class="mr-2 rounded bg-gray-100 px-2 py-0.5 font-mono text-base font-medium text-gray-600">{{ $course->code }}</span>
            {{ $course->title }}
        </h1>
        <p class="mt-1 text-sm text-gray-500">{{ $course->instructor->name }}</p>
        <p class="mt-3 max-w-2xl text-sm text-gray-600">{{ $course->description }}</p>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        @if ($course->forum)
            <a href="{{ route('forums.show', $course->forum) }}"
               class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Discussion forum
            </a>
        @endif
        @if ($isOwner)
            <a href="{{ route('courses.edit', $course) }}"
               class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Edit
            </a>
        @endif
        @if ($isEnrolled)
            <form method="post" action="{{ route('courses.unenrol', $course) }}"
                  onsubmit="return confirm('Leave this course?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Leave
                </button>
            </form>
        @endif
    </div>
</div>

@include('partials.flash')

<div class="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-3">

    <div class="space-y-6 lg:col-span-2">

        {{-- MATERIALS -- rendered entirely through the Adapter interface. --}}
        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-6 py-3">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-600">Materials</h2>
                @if ($isOwner)
                    <a href="{{ route('courses.materials.create', $course) }}"
                       class="text-sm font-medium text-blue-700 hover:text-blue-900">Add material</a>
                @endif
            </div>

            <ul class="divide-y divide-gray-100">
                @forelse ($materials as $entry)
                    @php $display = $entry['display']; @endphp
                    <li class="flex items-center gap-4 px-6 py-4">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                             class="h-6 w-6 shrink-0 text-gray-400">
                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $display->iconPath() }}" />
                        </svg>

                        <div class="min-w-0 flex-1">
                            <a href="{{ $display->url() }}"
                               @if ($display->opensExternally()) target="_blank" rel="noopener noreferrer" @endif
                               class="block truncate font-medium text-gray-900 hover:text-blue-700">
                                {{ $display->title() }}
                            </a>
                            <p class="text-xs text-gray-500">
                                <span class="capitalize">{{ $entry['material']->type }}</span>
                                &middot; {{ $display->kind() }} &middot; {{ $display->detail() }}
                            </p>
                        </div>

                        @if ($isOwner)
                            <form method="post"
                                  action="{{ route('courses.materials.destroy', [$course, $entry['material']]) }}"
                                  onsubmit="return confirm('Remove this material?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm text-red-700 hover:text-red-900">Remove</button>
                            </form>
                        @endif
                    </li>
                @empty
                    <li class="px-6 py-10 text-center text-sm text-gray-500">No materials yet.</li>
                @endforelse
            </ul>
        </section>

        {{-- QUIZZES --}}
        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-6 py-3">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-600">Quizzes</h2>
                @if ($isOwner)
                    <a href="{{ route('courses.quizzes.create', $course) }}"
                       class="text-sm font-medium text-blue-700 hover:text-blue-900">New quiz</a>
                @endif
            </div>
            <ul class="divide-y divide-gray-100">
                @forelse ($course->quizzes as $quiz)
                    <li class="flex items-center justify-between px-6 py-4">
                        <div>
                            <p class="font-medium text-gray-900">{{ $quiz->title }}</p>
                            <p class="text-xs text-gray-500">
                                {{ $quiz->questions->count() }} questions &middot; {{ $quiz->time_limit }} min
                            </p>
                        </div>
                        <a href="{{ route('quizzes.show', $quiz) }}"
                           class="text-sm font-medium text-blue-700 hover:text-blue-900">
                            {{ $isOwner ? 'Manage' : 'Open' }}
                        </a>
                    </li>
                @empty
                    <li class="px-6 py-10 text-center text-sm text-gray-500">No quizzes yet.</li>
                @endforelse
            </ul>
        </section>

        {{-- ASSIGNMENTS --}}
        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-6 py-3">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-600">Assignments</h2>
                @if ($isOwner)
                    <a href="{{ route('courses.assignments.create', $course) }}"
                       class="text-sm font-medium text-blue-700 hover:text-blue-900">New assignment</a>
                @endif
            </div>
            <ul class="divide-y divide-gray-100">
                @forelse ($course->assignments as $assignment)
                    <li class="flex items-center justify-between px-6 py-4">
                        <div>
                            <p class="font-medium text-gray-900">{{ $assignment->title }}</p>
                            <p class="text-xs {{ $assignment->isOverdue() ? 'text-red-600' : 'text-gray-500' }}">
                                Due {{ $assignment->due_date->format('j M Y, H:i') }}
                                @if ($assignment->isOverdue()) &middot; closed @endif
                            </p>
                        </div>
                        <a href="{{ route('assignments.show', $assignment) }}"
                           class="text-sm font-medium text-blue-700 hover:text-blue-900">
                            {{ $isOwner ? 'Review' : 'Open' }}
                        </a>
                    </li>
                @empty
                    <li class="px-6 py-10 text-center text-sm text-gray-500">No assignments yet.</li>
                @endforelse
            </ul>
        </section>

    </div>

    {{-- ANNOUNCEMENTS --}}
    <aside class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-200 bg-gray-50 px-5 py-3">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-600">Announcements</h2>
        </div>
        <ul class="divide-y divide-gray-100">
            @forelse ($course->announcements->sortByDesc('created_at') as $announcement)
                <li class="px-5 py-4">
                    <p class="text-sm text-gray-700">{{ $announcement->content }}</p>
                    <p class="mt-2 text-xs text-gray-400">
                        {{ $announcement->author->name }} &middot; {{ $announcement->created_at->diffForHumans() }}
                    </p>
                </li>
            @empty
                <li class="px-5 py-8 text-center text-sm text-gray-500">Nothing announced yet.</li>
            @endforelse
        </ul>
    </aside>

</div>

@endsection
