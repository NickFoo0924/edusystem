{{--
    LearnSync -- Blade view
    Module 1: Identity, Access & Digital Credentialing
    @author Serena Lim Sze Kee
--}}
{{-- learning_paths/edit.blade.php --}}
@extends('layout')

@section('title', 'Edit '.$path->title)

@section('content')

<a href="{{ route('learning-paths.index') }}" class="text-sm text-gray-500 hover:text-gray-800">
    &larr; Back to learning paths
</a>

<h1 class="mt-6 text-2xl font-semibold tracking-tight">Edit &ldquo;{{ $path->title }}&rdquo;</h1>

<form method="post" action="{{ route('learning-paths.update', $path) }}"
      class="mt-6 max-w-3xl rounded-xl border border-gray-200 bg-white p-8 shadow-sm">
    @csrf
    @method('PUT')

    @include('learning_paths._form')

    <div class="mt-8 flex items-center gap-3">
        <button type="submit"
                class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
            Save changes
        </button>
        <a href="{{ route('learning-paths.index') }}"
           class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
            Cancel
        </a>
    </div>
</form>

@endsection
