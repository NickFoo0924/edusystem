{{--
    LearnSync -- Blade view
    Module 4: Skill Assessment & Quiz
    @author Wong Siew Lam
--}}
{{-- quizzes/create.blade.php --}}
@extends('layout')

@section('title', 'New quiz')

@section('content')

<a href="{{ route('courses.show', $course) }}" class="text-sm text-gray-500 hover:text-gray-800">
    &larr; Back to {{ $course->title }}
</a>

<h1 class="mt-6 text-2xl font-semibold tracking-tight">New quiz</h1>

@include('partials.flash')

<form method="post" action="{{ route('courses.quizzes.store', $course) }}"
      class="mt-6 max-w-xl rounded-xl border border-gray-200 bg-white p-8 shadow-sm">
    @csrf

    <div class="space-y-5">
        <div>
            <label for="title" class="block text-sm font-medium text-gray-700">Title</label>
            <input id="title" name="title" type="text" required value="{{ old('title') }}"
                   class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
        </div>
        <div>
            <label for="time_limit" class="block text-sm font-medium text-gray-700">Time limit (minutes)</label>
            <input id="time_limit" name="time_limit" type="number" min="1" max="300" required
                   value="{{ old('time_limit', 20) }}"
                   class="mt-1 block w-32 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
        </div>
    </div>

    <div class="mt-8 flex items-center gap-3">
        <button type="submit" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
            Create quiz
        </button>
        <a href="{{ route('courses.show', $course) }}"
           class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
            Cancel
        </a>
    </div>
</form>

@endsection
