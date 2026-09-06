{{--
    LearnSync -- Blade view
    Module 2: Academic Resources Repository
    @author Foo Chong Xian
--}}
{{-- announcements/create.blade.php --}}
@extends('layout')

@section('title', 'Post announcement')

@section('content')

<a href="{{ route('announcements.index') }}" class="text-sm text-gray-500 hover:text-gray-800">
    &larr; Back to announcements
</a>

<h1 class="mt-6 text-2xl font-semibold tracking-tight">Post an announcement</h1>

@include('partials.flash')

<form method="post" action="{{ route('announcements.store') }}"
      class="mt-6 max-w-xl rounded-xl border border-gray-200 bg-white p-8 shadow-sm">
    @csrf

    <div class="space-y-5">
        <div>
            <label for="course_id" class="block text-sm font-medium text-gray-700">Audience</label>
            <select id="course_id" name="course_id"
                    class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                @if ($canBroadcastGlobally)
                    <option value="">Everyone (global announcement)</option>
                @endif
                @foreach ($courses as $course)
                    <option value="{{ $course->id }}" @selected((string) old('course_id') === (string) $course->id)>
                        {{ $course->title }}
                    </option>
                @endforeach
            </select>
            @unless ($canBroadcastGlobally)
                <p class="mt-1 text-xs text-gray-500">
                    Only an administrator can broadcast to everyone.
                </p>
            @endunless
        </div>

        <div>
            <label for="content" class="block text-sm font-medium text-gray-700">Message</label>
            <textarea id="content" name="content" rows="5" required maxlength="2000"
                      class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('content') }}</textarea>
        </div>
    </div>

    <div class="mt-8 flex items-center gap-3">
        <button type="submit" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
            Post
        </button>
        <a href="{{ route('announcements.index') }}"
           class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
            Cancel
        </a>
    </div>
</form>

@endsection
