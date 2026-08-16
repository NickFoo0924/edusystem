{{-- settings/edit.blade.php --}}
@extends('layout')

@section('title', 'System settings')

@section('content')

<h1 class="text-2xl font-semibold tracking-tight">System settings</h1>
<p class="mt-2 max-w-2xl text-sm text-gray-500">
    These are the numbers EduSystem.md forbids hardcoding. The CredentialAuthority reads them on every
    recalculation, so a change here alters scoring from the next grade onwards.
</p>

@include('partials.flash')

<form method="post" action="{{ route('settings.update') }}"
      class="mt-6 max-w-xl rounded-xl border border-gray-200 bg-white p-8 shadow-sm">
    @csrf
    @method('PUT')

    <div class="space-y-6">
        @foreach ($editable as $key => [$label, $help, $min, $max])
            <div>
                <label for="{{ $key }}" class="block text-sm font-medium text-gray-700">{{ $label }}</label>
                <div class="mt-1 flex items-center gap-2">
                    <input id="{{ $key }}" name="settings[{{ $key }}]" type="number" step="0.01"
                           min="{{ $min }}" max="{{ $max }}" required
                           value="{{ old('settings.'.$key, $values[$key] ?? '') }}"
                           class="block w-32 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <span class="text-sm text-gray-500">%</span>
                </div>
                <p class="mt-1 text-xs text-gray-500">{{ $help }}</p>
                <code class="text-xs text-gray-400">{{ $key }}</code>
            </div>
        @endforeach
    </div>

    <button type="submit"
            class="mt-8 rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
        Save settings
    </button>
</form>

@endsection
