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
        {{-- Clickable so a student can find how to contact their lecturer. --}}
        <p class="mt-1 text-sm">
            <a href="{{ route('instructors.show', $course->instructor) }}"
               class="inline-flex items-center gap-1 text-gray-500 underline decoration-gray-300 underline-offset-2 hover:text-blue-700 hover:decoration-blue-400">
                {{ $course->instructor->name }}
                <svg viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5 opacity-60">
                    <path d="M10 9a3 3 0 100-6 3 3 0 000 6zM6 8a2 2 0 11-4 0 2 2 0 014 0zM1.49 15.326a.78.78 0 01-.358-.442 3 3 0 014.308-3.516 6.484 6.484 0 00-1.905 3.959c-.023.222-.014.442.025.654a4.97 4.97 0 01-2.07-.655zM16.44 15.98a4.97 4.97 0 002.07-.654.78.78 0 00.357-.442 3 3 0 00-4.308-3.517 6.484 6.484 0 011.907 3.96 2.32 2.32 0 01-.026.654zM18 8a2 2 0 11-4 0 2 2 0 014 0zM5.304 16.19a.844.844 0 01-.277-.71 5 5 0 019.947 0 .843.843 0 01-.277.71A6.975 6.975 0 0110 18a6.974 6.974 0 01-4.696-1.81z" />
                </svg>
            </a>
        </p>
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

            {{-- The four categories always appear, empty or not, so the shape
                 of a course is the same everywhere in the system. --}}
            <div class="divide-y divide-gray-200">
                @foreach ($materialsByCategory as $type => $category)
                    <section>
                        <h3 class="bg-gray-50/60 px-6 py-2 text-xs font-semibold uppercase tracking-wide text-gray-500">
                            {{ $category['label'] }}
                            <span class="ml-1 font-normal normal-case text-gray-400">
                                ({{ count($category['items']) }})
                            </span>
                        </h3>

                        <ul class="divide-y divide-gray-100">
                            @forelse ($category['items'] as $entry)
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
                                            {{ $display->kind() }} &middot; {{ $display->detail() }}
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
                                <li class="px-6 py-3 text-sm text-gray-400">
                                    Nothing posted here yet.
                                    @if ($isOwner)
                                        <a href="{{ route('courses.materials.create', ['course' => $course, 'type' => $type]) }}"
                                           class="ml-1 font-medium text-blue-700 hover:text-blue-900">Add one</a>
                                    @endif
                                </li>
                            @endforelse
                        </ul>
                    </section>
                @endforeach
            </div>
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

    {{-- THE RIGHT-HAND COLUMN.

         One grid cell holding every side panel, stacked. It has to be a single
         child of the grid: left as siblings, the third panel would land in the
         next row's first column -- underneath the main content on the left,
         rather than under the panel above it on the right. --}}
    <div class="space-y-6">

    {{-- CLASS CODE AND ROSTER -- the owner's panel.

         Both ways into this course are driven from here: hand out the code, or
         name a student directly. Students never see this block, which is the
         point -- the code is only a control while it is the lecturer's to
         give. --}}
    @if ($isOwner)
        <aside class="space-y-6">

            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-200 bg-gray-50 px-5 py-3">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-600">Class code</h2>
                </div>
                <div class="px-5 py-4">
                    <p class="font-mono text-2xl font-semibold tracking-widest text-gray-900">{{ $course->class_code }}</p>
                    <p class="mt-2 text-xs text-gray-500">
                        Anyone with this code can join without an invitation. It is not the course
                        code &mdash; give it out only to the class.
                    </p>
                    <form method="post" action="{{ route('courses.class-code.rotate', $course) }}"
                          onsubmit="return confirm('Issue a new class code? The current one stops working immediately.');"
                          class="mt-3">
                        @csrf
                        <button type="submit" class="text-sm font-medium text-blue-700 hover:text-blue-900">
                            Issue a new code
                        </button>
                    </form>
                </div>
            </section>

            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-200 bg-gray-50 px-5 py-3">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-600">
                        Students ({{ $roster->count() }})
                    </h2>
                </div>

                <ul class="divide-y divide-gray-100">
                    @forelse ($roster as $student)
                        <li class="flex items-center gap-3 px-5 py-3">
                            <x-avatar :user="$student" size="sm" />
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-gray-900">{{ $student->name }}</p>
                                <p class="truncate text-xs text-gray-500">{{ $student->email }}</p>
                            </div>
                        </li>
                    @empty
                        <li class="px-5 py-8 text-center text-sm text-gray-500">Nobody has joined yet.</li>
                    @endforelse
                </ul>

                @if ($pendingInvitations->isNotEmpty())
                    <div class="border-t border-gray-200 bg-gray-50 px-5 py-2">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Invited, not yet accepted
                        </h3>
                    </div>
                    <ul class="divide-y divide-gray-100">
                        @foreach ($pendingInvitations as $invitation)
                            <li class="flex items-center justify-between gap-3 px-5 py-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm text-gray-700">{{ $invitation->student->name }}</p>
                                    <p class="truncate text-xs text-gray-400">
                                        invited {{ $invitation->created_at->diffForHumans() }}
                                    </p>
                                </div>
                                <form method="post"
                                      action="{{ route('courses.invitations.destroy', [$course, $invitation]) }}"
                                      onsubmit="return confirm('Withdraw this invitation?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-medium text-gray-500 hover:text-red-700">
                                        Withdraw
                                    </button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <div class="border-t border-gray-200 px-5 py-4">
                    <form method="post" action="{{ route('courses.invitations.store', $course) }}">
                        @csrf
                        <label for="email" class="block text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Invite a student
                        </label>
                        <div class="mt-2 flex gap-2">
                            <input type="email" name="email" id="email" required
                                   placeholder="student@example.com"
                                   class="min-w-0 flex-1 rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <button type="submit"
                                    class="shrink-0 rounded-lg bg-blue-700 px-3 py-2 text-sm font-medium text-white hover:bg-blue-800">
                                Invite
                            </button>
                        </div>
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </form>
                </div>
            </section>

        </aside>
    @endif

    {{-- ANNOUNCEMENTS --}}
    <aside class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-200 bg-gray-50 px-5 py-3">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-600">Announcements</h2>
        </div>
        <ul class="divide-y divide-gray-100">
            @forelse ($course->announcements->sortByDesc('created_at') as $announcement)
                <li id="announcement-{{ $announcement->id }}" class="scroll-mt-4 px-5 py-4">
                    <p class="text-sm text-gray-700">{{ $announcement->content }}</p>
                    <p class="mt-2 text-xs text-gray-400">
                        {{ $announcement->author->name }} &middot; {{ $announcement->created_at->diffForHumans() }}
                    </p>

                    @include('partials.announcement-comments')
                </li>
            @empty
                <li class="px-5 py-8 text-center text-sm text-gray-500">Nothing announced yet.</li>
            @endforelse
        </ul>
    </aside>

    </div>{{-- /right-hand column --}}

</div>

@endsection
