{{-- calendar/create.blade.php --}}
@extends('layout')

@section('title', 'Schedule an event')

@section('content')

<a href="{{ route('calendar.index') }}" class="text-sm text-gray-500 hover:text-gray-800">
    &larr; Back to the calendar
</a>

<h1 class="mt-6 text-2xl font-semibold tracking-tight">Schedule an event</h1>
<p class="mt-2 max-w-2xl text-sm text-gray-500">
    A class, an online meeting or a briefing. Assignment deadlines do not belong here &mdash; they
    appear on the calendar by themselves, from the due date set on the assignment.
</p>

@include('partials.flash')

<form method="post" action="{{ route('events.store') }}"
      class="mt-6 max-w-xl rounded-xl border border-gray-200 bg-white p-8 shadow-sm">
    @csrf

    <div class="space-y-5">
        <div>
            <label for="course_id" class="block text-sm font-medium text-gray-700">Course</label>
            <select id="course_id" name="course_id"
                    class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                @if ($canScheduleGlobally)
                    <option value="">Everyone (institution-wide)</option>
                @endif
                @foreach ($courses as $course)
                    <option value="{{ $course->id }}" @selected((string) old('course_id') === (string) $course->id)>
                        {{ $course->code }} {{ $course->title }}
                    </option>
                @endforeach
            </select>
            @unless ($canScheduleGlobally)
                <p class="mt-1 text-xs text-gray-500">
                    You can schedule for the courses you teach.
                </p>
            @endunless
            <x-input-error :messages="$errors->get('course_id')" class="mt-2" />
        </div>

        <div>
            <label for="title" class="block text-sm font-medium text-gray-700">Title</label>
            <input type="text" id="title" name="title" required maxlength="255" value="{{ old('title') }}"
                   placeholder="Week 3 lecture"
                   class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            <x-input-error :messages="$errors->get('title')" class="mt-2" />
        </div>

        <div>
            <label for="type" class="block text-sm font-medium text-gray-700">Kind</label>
            <select id="type" name="type"
                    class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                @foreach ($types as $value => $label)
                    <option value="{{ $value }}" @selected(old('type', 'class') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('type')" class="mt-2" />
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label for="starts_at" class="block text-sm font-medium text-gray-700">Starts</label>
                <input type="datetime-local" id="starts_at" name="starts_at" required value="{{ old('starts_at') }}"
                       class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <x-input-error :messages="$errors->get('starts_at')" class="mt-2" />
            </div>

            <div>
                <label for="ends_at" class="block text-sm font-medium text-gray-700">
                    Ends <span class="font-normal text-gray-400">(optional)</span>
                </label>
                <input type="datetime-local" id="ends_at" name="ends_at" value="{{ old('ends_at') }}"
                       class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <x-input-error :messages="$errors->get('ends_at')" class="mt-2" />
            </div>
        </div>

        <div>
            <label for="meeting_url" class="block text-sm font-medium text-gray-700">
                Meeting link <span class="font-normal text-gray-400">(optional)</span>
            </label>
            <input type="url" id="meeting_url" name="meeting_url" value="{{ old('meeting_url') }}"
                   placeholder="https://meet.google.com/abc-defg-hij"
                   class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            <p class="mt-1 text-xs text-gray-500">
                Given one, the entry on the calendar links straight to it.
            </p>
            <x-input-error :messages="$errors->get('meeting_url')" class="mt-2" />
        </div>

        <div>
            <label for="location" class="block text-sm font-medium text-gray-700">
                Room <span class="font-normal text-gray-400">(optional)</span>
            </label>
            <input type="text" id="location" name="location" maxlength="255" value="{{ old('location') }}"
                   placeholder="D303B"
                   class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            <x-input-error :messages="$errors->get('location')" class="mt-2" />
        </div>

        <div>
            <label for="description" class="block text-sm font-medium text-gray-700">
                Notes <span class="font-normal text-gray-400">(optional)</span>
            </label>
            <textarea id="description" name="description" rows="3" maxlength="2000"
                      class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('description') }}</textarea>
            <x-input-error :messages="$errors->get('description')" class="mt-2" />
        </div>
    </div>

    <button type="submit"
            class="mt-6 rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
        Schedule
    </button>
</form>

@endsection
