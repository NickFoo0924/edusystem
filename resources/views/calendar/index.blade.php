{{-- calendar/index.blade.php

     Every row on this page is a CalendarEntry. The grid never asks whether an
     entry came from `course_events` or from an assignment's due_date -- that
     distinction lives in app/Patterns/Adapter and stops there. --}}
@extends('layout')

@section('title', 'Calendar')

@php
    // One place deciding whether a link leaves the system, used by both the
    // grid and the list below.
    $isExternal = fn (?string $url) => $url
        && Str::startsWith($url, ['http://', 'https://'])
        && ! Str::contains($url, request()->getHost());
@endphp

@section('content')

<div class="flex flex-wrap items-center justify-between gap-4">
    <h1 class="text-2xl font-semibold tracking-tight">Calendar</h1>

    @if ($canSchedule)
        <a href="{{ route('events.create') }}"
           class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
            Schedule an event
        </a>
    @endif
</div>

@include('partials.flash')

{{-- Month navigation. Plain links carrying ?month=, so paging the calendar is
     a normal page load that can be bookmarked and gone back to. --}}
<div class="mt-6 flex items-center gap-2">
    <a href="{{ route('calendar.index', ['month' => $month->copy()->subMonth()->format('Y-m')]) }}"
       aria-label="Previous month"
       class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">&larr;</a>

    <a href="{{ route('calendar.index') }}"
       class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
        Today
    </a>

    <a href="{{ route('calendar.index', ['month' => $month->copy()->addMonth()->format('Y-m')]) }}"
       aria-label="Next month"
       class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">&rarr;</a>

    <h2 class="ms-2 text-lg font-semibold text-gray-900">{{ $month->format('F Y') }}</h2>
</div>

<div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-4">

    {{-- THE MONTH GRID --}}
    <div class="lg:col-span-3">
        <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="min-w-[46rem]">
                <div class="grid grid-cols-7 border-b border-gray-200 bg-gray-50">
                    @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday)
                        <div class="px-2 py-2 text-center text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                            {{ $weekday }}
                        </div>
                    @endforeach
                </div>

                @foreach ($days->chunk(7) as $week)
                    <div class="grid grid-cols-7 border-b border-gray-100 last:border-b-0">
                        @foreach ($week as $day)
                            @php
                                $entries = $entriesByDay->get($day->format('Y-m-d'), collect());
                                $isThisMonth = $day->month === $month->month;
                                $isToday = $day->isSameDay($today);
                            @endphp

                            <div class="min-h-[7rem] border-e border-gray-100 p-1.5 last:border-e-0 {{ $isThisMonth ? '' : 'bg-gray-50' }}">
                                <div class="mb-1 flex justify-end">
                                    <span class="inline-flex h-6 min-w-[1.5rem] items-center justify-center rounded-full px-1.5 text-xs {{ $isToday ? 'bg-blue-700 font-semibold text-white' : ($isThisMonth ? 'text-gray-700' : 'text-gray-400') }}">
                                        {{ $day->day }}
                                    </span>
                                </div>

                                <div class="space-y-1">
                                    {{-- Three per cell, then a count. A busy day
                                         must not stretch the row until the rest
                                         of the month is pushed off screen. --}}
                                    @foreach ($entries->take(3) as $entry)
                                        @if ($entry->url())
                                            <a href="{{ $entry->url() }}"
                                               @if ($isExternal($entry->url())) target="_blank" rel="noopener noreferrer" @endif
                                               title="{{ $entry->kind() }} &middot; {{ $entry->detail() }}"
                                               class="block truncate rounded border px-1.5 py-1 text-[11px] leading-tight {{ $entry->classes() }}">
                                                <span class="font-medium">{{ $entry->startsAt()->format('g:ia') }}</span>
                                                @if ($entry->courseLabel())
                                                    <span class="font-medium">{{ $entry->courseLabel() }}</span>
                                                @endif
                                                {{ $entry->title() }}
                                            </a>
                                        @else
                                            <span title="{{ $entry->kind() }} &middot; {{ $entry->detail() }}"
                                                  class="block truncate rounded border px-1.5 py-1 text-[11px] leading-tight {{ $entry->classes() }}">
                                                <span class="font-medium">{{ $entry->startsAt()->format('g:ia') }}</span>
                                                @if ($entry->courseLabel())
                                                    <span class="font-medium">{{ $entry->courseLabel() }}</span>
                                                @endif
                                                {{ $entry->title() }}
                                            </span>
                                        @endif
                                    @endforeach

                                    @if ($entries->count() > 3)
                                        <span class="block px-1.5 text-[11px] text-gray-500">
                                            +{{ $entries->count() - 3 }} more
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>

        <p class="mt-3 flex flex-wrap gap-3 text-xs text-gray-500">
            <span class="inline-flex items-center gap-1.5">
                <span class="inline-block h-2.5 w-2.5 rounded-sm border border-blue-300 bg-blue-50"></span> Class
            </span>
            <span class="inline-flex items-center gap-1.5">
                <span class="inline-block h-2.5 w-2.5 rounded-sm border border-violet-300 bg-violet-50"></span> Online meeting
            </span>
            <span class="inline-flex items-center gap-1.5">
                <span class="inline-block h-2.5 w-2.5 rounded-sm border border-amber-300 bg-amber-50"></span> Assignment due
            </span>
        </p>
    </div>

    {{-- WHAT IS COMING UP -- not month-bound, because a deadline three days
         away should not vanish when you page back to last month. --}}
    <aside class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm lg:self-start">
        <div class="border-b border-gray-200 bg-gray-50 px-5 py-3">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-600">Next 14 days</h2>
        </div>

        <ul class="divide-y divide-gray-100">
            @forelse ($upcoming as $entry)
                <li class="px-5 py-3">
                    <div class="flex items-baseline justify-between gap-2">
                        <span class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ $entry->kind() }}</span>
                        @if ($entry->courseLabel())
                            <span class="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-[10px] text-gray-600">
                                {{ $entry->courseLabel() }}
                            </span>
                        @endif
                    </div>

                    <p class="mt-0.5 text-sm font-medium text-gray-900">
                        @if ($entry->url())
                            <a href="{{ $entry->url() }}"
                               @if ($isExternal($entry->url())) target="_blank" rel="noopener noreferrer" @endif
                               class="hover:text-blue-700">{{ $entry->title() }}</a>
                        @else
                            {{ $entry->title() }}
                        @endif
                    </p>

                    <p class="mt-0.5 text-xs text-gray-500">
                        {{ $entry->startsAt()->format('D j M') }} &middot; {{ $entry->detail() }}
                    </p>
                </li>
            @empty
                <li class="px-5 py-8 text-center text-sm text-gray-500">
                    Nothing scheduled in the next two weeks.
                </li>
            @endforelse
        </ul>
    </aside>

</div>

@endsection
