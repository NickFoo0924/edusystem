{{--
    LearnSync -- Blade view
    Module 2: Academic Resources Repository
    @author Foo Chong Xian
--}}
{{-- materials/create.blade.php --}}
@extends('layout')

@section('title', 'Add material')

@section('content')

<a href="{{ route('courses.show', $course) }}" class="text-sm text-gray-500 hover:text-gray-800">
    &larr; Back to {{ $course->title }}
</a>

<h1 class="mt-6 text-2xl font-semibold tracking-tight">Add material</h1>
<p class="mt-2 max-w-2xl text-sm text-gray-500">
    Upload a file or link to something external. Both appear in one list for students &mdash; the Adapter
    pattern makes an external link present itself exactly like an uploaded file.
</p>

@include('partials.flash')

<form method="post" action="{{ route('courses.materials.store', $course) }}" enctype="multipart/form-data"
      class="mt-6 max-w-xl rounded-xl border border-gray-200 bg-white p-8 shadow-sm">
    @csrf

    <div class="space-y-5">

        <div>
            <label for="title" class="block text-sm font-medium text-gray-700">Title</label>
            <input id="title" name="title" type="text" required value="{{ old('title') }}"
                   class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
        </div>

        <div>
            <label for="type" class="block text-sm font-medium text-gray-700">Category</label>
            <select id="type" name="type"
                    class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                @foreach (\App\Models\CourseMaterial::CATEGORIES as $value => $label)
                    <option value="{{ $value }}" @selected(old('type', request('type')) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <fieldset>
            <legend class="text-sm font-medium text-gray-700">Source</legend>
            <div class="mt-2 space-y-3">
                <label class="flex items-start gap-3 rounded-lg border border-gray-200 p-3">
                    <input type="radio" name="source" value="file" @checked(old('source', 'file') === 'file')
                           onchange="document.getElementById('file-row').hidden = false; document.getElementById('link-row').hidden = true;"
                           class="mt-1 border-gray-300 text-blue-700 focus:ring-blue-500">
                    <span>
                        <span class="block text-sm font-medium text-gray-900">Upload a file</span>
                        <span class="block text-xs text-gray-500">PDF, slides, worksheet. Up to 20 MB.</span>
                    </span>
                </label>
                <label class="flex items-start gap-3 rounded-lg border border-gray-200 p-3">
                    <input type="radio" name="source" value="link" @checked(old('source') === 'link')
                           onchange="document.getElementById('file-row').hidden = true; document.getElementById('link-row').hidden = false;"
                           class="mt-1 border-gray-300 text-blue-700 focus:ring-blue-500">
                    <span>
                        <span class="block text-sm font-medium text-gray-900">Link to an external resource</span>
                        <span class="block text-xs text-gray-500">A YouTube video, an article, online documentation.</span>
                    </span>
                </label>
            </div>
        </fieldset>

        <div id="file-row" @if (old('source') === 'link') hidden @endif>
            <label for="file" class="block text-sm font-medium text-gray-700">File</label>
            <input id="file" name="file" type="file"
                   class="mt-1 block w-full text-sm text-gray-600 file:mr-4 file:rounded-lg file:border-0 file:bg-gray-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-gray-700 hover:file:bg-gray-200">
        </div>

        <div id="link-row" @if (old('source') !== 'link') hidden @endif>
            <label for="url" class="block text-sm font-medium text-gray-700">Address</label>
            <input id="url" name="url" type="url" value="{{ old('url') }}" placeholder="https://www.youtube.com/watch?v=…"
                   class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
        </div>

    </div>

    <div class="mt-8 flex items-center gap-3">
        <button type="submit" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
            Add material
        </button>
        <a href="{{ route('courses.show', $course) }}"
           class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
            Cancel
        </a>
    </div>

</form>

@endsection
