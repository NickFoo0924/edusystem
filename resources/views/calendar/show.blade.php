{{-- calendar/show.blade.php -- one scheduled event, in full.

     Reached by clicking any entry on the calendar grid. Deliberately a page
     rather than a jump straight to the meeting: a click should not drop
     somebody into a live call, and not every event has a link to jump to. --}}
@extends('layout')

@section('title', $event->title)

@section('content')

<a href="{{ route('calendar.index', ['month' => $event->starts_at->format('Y-m')]) }}"
   class="text-sm text-gray-500 hover:text-gray-800">
    &larr; Back to the calendar
</a>

@include('partials.flash')

<div class="mt-6 max-w-3xl overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">

    <div class="border-b border-gray-200 px-8 py-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-full px-2.5 py-0.5 text-[11px] font-medium uppercase tracking-wide
                                 {{ match ($event->type) {
                                     'meeting' => 'bg-violet-100 text-violet-800',
                                     'other' => 'bg-gray-100 text-gray-700',
                                     default => 'bg-blue-100 text-blue-800',
                                 } }}">
                        {{ \App\Models\CourseEvent::TYPES[$event->type] ?? 'Event' }}
                    </span>

                    @if ($event->isGlobal())
                        <span class="rounded-full bg-indigo-100 px-2.5 py-0.5 text-[11px] font-medium uppercase tracking-wide text-indigo-800">
                            Institution-wide
                        </span>
                    @elseif ($event->course)
                        <a href="{{ route('courses.show', $event->course) }}"
                           class="rounded-full bg-gray-100 px-2.5 py-0.5 text-[11px] font-medium text-gray-700 hover:bg-gray-200">
                            {{ $event->course->label() }}
                        </a>
                    @endif
                </div>

                <h1 class="mt-3 text-2xl font-semibold tracking-tight text-gray-900">{{ $event->title }}</h1>
            </div>

            {{-- The join button, and ONLY when there is something usable to
                 join. A class in a room has no link, so no button is drawn --
                 never a dead one. --}}
            @if ($joinUrl)
                <a href="{{ $joinUrl }}" target="_blank" rel="noopener noreferrer"
                   class="shrink-0 rounded-lg bg-blue-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-blue-800">
                    Join meeting &nearr;
                </a>
            @endif
        </div>
    </div>

    <dl class="divide-y divide-gray-100">

        <div class="grid grid-cols-1 gap-1 px-8 py-4 sm:grid-cols-3">
            <dt class="text-sm font-medium text-gray-500">When</dt>
            <dd class="text-sm text-gray-900 sm:col-span-2">
                {{ $event->starts_at->format('l j F Y') }}<br>
                <span class="text-gray-600">
                    {{ $event->starts_at->format('g:ia') }}
                    @if ($event->ends_at)
                        &ndash; {{ $event->ends_at->format('g:ia') }}
                        <span class="text-gray-400">
                            ({{ $event->starts_at->diffForHumans($event->ends_at, ['parts' => 2, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE]) }})
                        </span>
                    @endif
                </span>
                <span class="mt-1 block text-xs text-gray-400">
                    {{ $event->starts_at->isPast() ? 'This has already taken place' : $event->starts_at->diffForHumans() }}
                </span>
            </dd>
        </div>

        @if ($event->location)
            <div class="grid grid-cols-1 gap-1 px-8 py-4 sm:grid-cols-3">
                <dt class="text-sm font-medium text-gray-500">Where</dt>
                <dd class="text-sm text-gray-900 sm:col-span-2">{{ $event->location }}</dd>
            </div>
        @endif

        <div class="grid grid-cols-1 gap-1 px-8 py-4 sm:grid-cols-3">
            <dt class="text-sm font-medium text-gray-500">Organiser</dt>
            <dd class="text-sm text-gray-900 sm:col-span-2">
                @if ($event->creator && $event->creator->hasPublicProfile())
                    <a href="{{ route('instructors.show', $event->creator) }}"
                       class="font-medium text-blue-700 hover:text-blue-900">
                        {{ $event->creator->name }}
                    </a>
                @else
                    {{ $event->creator->name ?? 'Unknown' }}
                @endif
            </dd>
        </div>

        <div class="grid grid-cols-1 gap-1 px-8 py-4 sm:grid-cols-3">
            <dt class="text-sm font-medium text-gray-500">Who it concerns</dt>
            <dd class="text-sm text-gray-900 sm:col-span-2">
                {{ $audience['label'] }}
                @if ($audience['names']->isNotEmpty())
                    {{-- Names are shown to whoever runs the class. A student
                         sees the count instead, the same line the course roster
                         already draws. --}}
                    <ul class="mt-2 space-y-0.5 text-sm text-gray-600">
                        @foreach ($audience['names'] as $name)
                            <li>{{ $name }}</li>
                        @endforeach
                    </ul>
                @elseif ($audience['count'] > 0)
                    <span class="mt-1 block text-xs text-gray-500">
                        {{ $audience['count'] }} {{ Str::plural('student', $audience['count']) }} enrolled
                    </span>
                @endif
            </dd>
        </div>

        @if (filled($event->description))
            <div class="grid grid-cols-1 gap-1 px-8 py-4 sm:grid-cols-3">
                <dt class="text-sm font-medium text-gray-500">Details</dt>
                <dd class="whitespace-pre-line text-sm text-gray-700 sm:col-span-2">{{ $event->description }}</dd>
            </div>
        @endif

        @if ($joinUrl)
            <div class="grid grid-cols-1 gap-1 px-8 py-4 sm:grid-cols-3">
                <dt class="text-sm font-medium text-gray-500">Meeting link</dt>
                <dd class="min-w-0 text-sm sm:col-span-2">
                    <a href="{{ $joinUrl }}" target="_blank" rel="noopener noreferrer"
                       class="block truncate text-blue-700 hover:text-blue-900">{{ $joinUrl }}</a>
                </dd>
            </div>
        @elseif ($meetingUrlIsBroken)
            {{-- Said out loud rather than silently dropped, so whoever
                 scheduled it can fix it. Never rendered as a link. --}}
            <div class="grid grid-cols-1 gap-1 px-8 py-4 sm:grid-cols-3">
                <dt class="text-sm font-medium text-gray-500">Meeting link</dt>
                <dd class="text-sm text-amber-700 sm:col-span-2">
                    A meeting link was saved for this event but it is not a usable web address,
                    so it has not been shown. Ask the organiser to re-enter it.
                </dd>
            </div>
        @endif
    </dl>

    @if ($canDelete)
        <div class="border-t border-gray-200 bg-gray-50 px-8 py-4">
            <form method="post" action="{{ route('events.destroy', $event) }}"
                  onsubmit="return confirm('Remove this event from the calendar?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-sm font-medium text-red-700 hover:text-red-900">
                    Remove from calendar
                </button>
            </form>
        </div>
    @endif

</div>

@endsection
