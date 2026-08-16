{{-- assignments/edit.blade.php --}}
@extends('layout')

@section('title', 'Edit '.$assignment->title)

@section('content')

<a href="{{ route('assignments.show', $assignment) }}" class="text-sm text-gray-500 hover:text-gray-800">
    &larr; Back to {{ $assignment->title }}
</a>

<h1 class="mt-6 text-2xl font-semibold tracking-tight">Edit &ldquo;{{ $assignment->title }}&rdquo;</h1>
<p class="mt-2 max-w-2xl text-sm text-gray-500">
    Changing the policy takes effect immediately. Work already handed in keeps whatever late status it was
    given at the time.
</p>

@include('partials.flash')

<form method="post" action="{{ route('assignments.update', $assignment) }}"
      class="mt-6 max-w-xl rounded-xl border border-gray-200 bg-white p-8 shadow-sm">
    @csrf
    @method('PUT')

    @include('assignments._form')

    <div class="mt-8 flex items-center gap-3">
        <button type="submit" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
            Save changes
        </button>
        <a href="{{ route('assignments.show', $assignment) }}"
           class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
            Cancel
        </a>
    </div>
</form>

@endsection
