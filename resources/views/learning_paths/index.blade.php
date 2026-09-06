{{--
    LearnSync -- Blade view
    Module 1: Identity, Access & Digital Credentialing
    @author Serena Lim Sze Kee
--}}
{{-- learning_paths/index.blade.php --}}
@extends('layout')

@section('title', 'Learning Paths')

@section('content')

<div class="flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight">Learning Paths</h1>
        <p class="mt-2 text-sm text-gray-500">
            An ordered run of courses. Finishing all of them mints a pathway certificate automatically.
        </p>
    </div>
    <a href="{{ route('learning-paths.create') }}"
       class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
        New path
    </a>
</div>

@if (session('success'))
    <div class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
        {{ session('success') }}
    </div>
@endif
@if (session('error'))
    <div class="mt-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        {{ session('error') }}
    </div>
@endif

<div class="mt-8 space-y-4">

    @forelse ($paths as $path)
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">

            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <h2 class="font-semibold text-gray-900">{{ $path->title }}</h2>
                        @if ($path->is_active)
                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-emerald-800">Active</span>
                        @else
                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-gray-500">Inactive</span>
                        @endif
                    </div>
                    <p class="mt-1 text-sm text-gray-600">{{ $path->description }}</p>
                </div>

                <div class="flex items-center gap-3 text-sm">
                    <span class="text-xs text-gray-500">{{ $path->certificates_count }} issued</span>
                    <a href="{{ route('learning-paths.edit', $path) }}"
                       class="font-medium text-blue-700 hover:text-blue-900">Edit</a>
                    <form method="post" action="{{ route('learning-paths.destroy', $path) }}"
                          onsubmit="return confirm('Delete this learning path?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="font-medium text-red-700 hover:text-red-900">Delete</button>
                    </form>
                </div>
            </div>

            <ol class="mt-4 flex flex-wrap items-center gap-2">
                @foreach ($path->courses as $course)
                    <li class="flex items-center gap-2">
                        <span class="rounded-lg bg-gray-100 px-3 py-1.5 text-xs font-medium text-gray-700">
                            {{ $course->pivot->sequence }}. {{ $course->title }}
                        </span>
                        @unless ($loop->last)
                            <span class="text-gray-300">&rarr;</span>
                        @endunless
                    </li>
                @endforeach
            </ol>

        </div>
    @empty
        <div class="rounded-xl border border-dashed border-gray-300 bg-white p-10 text-center">
            <p class="text-sm text-gray-500">No learning paths defined yet.</p>
        </div>
    @endforelse

</div>

@endsection
