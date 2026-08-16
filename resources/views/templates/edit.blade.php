{{-- templates/edit.blade.php --}}
@extends('layout')

@section('title', 'Edit '.$template->name)

@section('content')

<a href="{{ route('templates.index') }}" class="text-sm text-gray-500 hover:text-gray-800">&larr; Back to templates</a>

<h1 class="mt-6 text-2xl font-semibold tracking-tight">Edit &ldquo;{{ $template->name }}&rdquo;</h1>
<p class="mt-2 text-sm text-gray-500">
    Editing a template does not change certificates already issued from it &mdash; their PDFs and
    integrity hashes are fixed at issuance.
</p>

@include('partials.flash')

<form method="post" action="{{ route('templates.update', $template) }}" enctype="multipart/form-data"
      class="mt-6 max-w-2xl rounded-xl border border-gray-200 bg-white p-8 shadow-sm">
    @csrf
    @method('PUT')
    @include('templates._form')

    <div class="mt-8 flex items-center gap-3">
        <button type="submit" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
            Save changes
        </button>
        <a href="{{ route('templates.index') }}"
           class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
            Cancel
        </a>
    </div>
</form>

@endsection
