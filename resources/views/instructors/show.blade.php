{{--
    LearnSync -- Blade view
    Module 1: Identity, Access & Digital Credentialing
    @author Serena Lim Sze Kee
--}}
{{-- instructors/show.blade.php -- read-only lecturer contact card --}}
@extends('layout')

@section('title', $instructor->name)

@section('content')

<div class="mx-auto max-w-2xl">

    <a href="{{ url()->previous() }}" class="text-sm text-gray-500 hover:text-gray-800">&larr; Back</a>

    <div class="mt-6 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">

        <div class="flex flex-wrap items-center gap-5 border-b border-gray-200 bg-gray-50 px-8 py-6">
            <x-avatar :user="$instructor" size="lg" />

            <div class="min-w-0">
                <h1 class="text-2xl font-semibold tracking-tight">{{ $instructor->name }}</h1>
                <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-gray-500">
                    <span class="rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-blue-800">
                        Lecturer
                    </span>
                    {{ $instructor->courses_teaching_count }}
                    {{ Str::plural('course', $instructor->courses_teaching_count) }}
                    @if ($sharesCourse)
                        <span class="text-emerald-700">&middot; teaches you</span>
                    @endif
                </p>
            </div>
        </div>

        @if ($instructor->bio)
            <div class="border-b border-gray-100 px-8 py-5">
                <p class="whitespace-pre-line text-sm text-gray-600">{{ $instructor->bio }}</p>
            </div>
        @endif

        {{-- CONTACT. Email is always here; the phone number appears only if its
             owner chose to publish one. --}}
        <div class="px-8 py-6">
            <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Contact</h2>

            <dl class="mt-3 space-y-3 text-sm">
                <div class="flex flex-wrap items-center gap-3">
                    <dt class="w-16 shrink-0 text-gray-500">Email</dt>
                    <dd>
                        {{-- The institutional address when there is one, so a
                             student is never handed a personal inbox. --}}
                        <a href="mailto:{{ $instructor->contactEmail() }}"
                           class="font-medium text-blue-700 hover:text-blue-900">{{ $instructor->contactEmail() }}</a>
                    </dd>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <dt class="w-16 shrink-0 text-gray-500">Phone</dt>
                    <dd>
                        @if ($phone)
                            <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}"
                               class="font-medium text-blue-700 hover:text-blue-900">{{ $phone }}</a>
                        @else
                            <span class="text-gray-400">Not shared — please use email</span>
                        @endif
                    </dd>
                </div>
            </dl>

            <p class="mt-5 rounded-lg bg-gray-50 px-4 py-3 text-xs text-gray-500">
                These details are shown so you can reach your lecturer about coursework. Please keep
                to reasonable hours and use email unless the matter is urgent.
            </p>
        </div>

        {{-- What they teach, so a student can confirm they have the right person. --}}
        <div class="border-t border-gray-100 px-8 py-6">
            <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Teaches</h2>
            <ul class="mt-3 space-y-2">
                @forelse ($courses as $course)
                    <li class="flex flex-wrap items-center justify-between gap-2">
                        <span class="flex items-center gap-2 text-sm">
                            <span class="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-xs font-medium text-gray-600">
                                {{ $course->code }}
                            </span>
                            <span class="text-gray-900">{{ $course->title }}</span>
                        </span>
                        <span class="text-xs text-gray-400">{{ $course->students_count }} enrolled</span>
                    </li>
                @empty
                    <li class="text-sm text-gray-500">No courses assigned.</li>
                @endforelse
            </ul>
        </div>

    </div>

    <p class="mt-4 text-center text-xs text-gray-400">
        This page is read-only. Only {{ $instructor->name }} can change these details.
    </p>

</div>

@endsection
