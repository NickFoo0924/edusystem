{{-- courses/create.blade.php --}}
@extends('layout')

@section('title', 'New Course')

@section('content')

<a href="{{ route('courses.index') }}" class="text-sm text-gray-500 hover:text-gray-800">&larr; Back to courses</a>

<h1 class="mt-6 text-2xl font-semibold tracking-tight">New Course</h1>

@include('partials.flash')

<form method="post" action="{{ route('courses.store') }}"
      class="mt-6 max-w-xl rounded-xl border border-gray-200 bg-white p-8 shadow-sm">
    @csrf

    <div class="space-y-5">
        <div>
            <label for="code" class="block text-sm font-medium text-gray-700">Course code</label>
            <input id="code" name="code" type="text" required maxlength="20" placeholder="BMIT3173"
                   value="{{ old('code', $course->code ?? '') }}"
                   class="mt-1 block w-full rounded-lg border-gray-300 font-mono uppercase shadow-sm focus:border-blue-500 focus:ring-blue-500">
        </div>
        <div>
            <label for="title" class="block text-sm font-medium text-gray-700">Title</label>
            <input id="title" name="title" type="text" required value="{{ old('title') }}"
                   class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
        </div>
        <div>
            <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
            <textarea id="description" name="description" rows="4" required
                      class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('description') }}</textarea>
        </div>
    </div>

    <p class="mt-4 text-xs text-gray-500">A Q&amp;A forum is created with the course automatically.</p>

    <div class="mt-8 flex items-center gap-3">
        <button type="submit" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
            Create course
        </button>
        <a href="{{ route('courses.index') }}"
           class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
            Cancel
        </a>
    </div>
</form>

@endsection
