{{--
    LearnSync -- Blade view
    Module 5: Academic Progress Analytics
    @author Ong Kwong Wei
--}}
{{-- assignments/create.blade.php --}}
@extends('layout')

@section('title', 'New assignment')

@section('content')

<a href="{{ route('courses.show', $course) }}" class="text-sm text-gray-500 hover:text-gray-800">
    &larr; Back to {{ $course->title }}
</a>

<h1 class="mt-6 text-2xl font-semibold tracking-tight">New assignment</h1>

@include('partials.flash')

<form method="post" action="{{ route('courses.assignments.store', $course) }}"
      class="mt-6 max-w-xl rounded-xl border border-gray-200 bg-white p-8 shadow-sm">
    @csrf

    @include('assignments._form', ['assignment' => null])

    <div class="mt-8 flex items-center gap-3">
        <button type="submit" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
            Create assignment
        </button>
        <a href="{{ route('courses.show', $course) }}"
           class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
            Cancel
        </a>
    </div>
</form>

@endsection
