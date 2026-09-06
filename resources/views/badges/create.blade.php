{{--
    LearnSync -- Blade view
    Module 1: Identity, Access & Digital Credentialing
    @author Serena Lim Sze Kee
--}}
{{-- badges/create.blade.php --}}
@extends('layout')

@section('title', 'New Badge')

@section('content')

<a href="{{ route('badges.index') }}" class="text-sm text-gray-500 hover:text-gray-800">
    &larr; Back to badge rules
</a>

<h1 class="mt-6 text-2xl font-semibold tracking-tight">New Badge Rule</h1>

<form method="post" action="{{ route('badges.store') }}" enctype="multipart/form-data"
      class="mt-6 rounded-xl border border-gray-200 bg-white p-8 shadow-sm">
    @csrf

    @include('badges._form', ['badge' => null])

    <div class="mt-8 flex items-center gap-3">
        <button type="submit"
                class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
            Create badge
        </button>
        <a href="{{ route('badges.index') }}"
           class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
            Cancel
        </a>
    </div>
</form>

@endsection
