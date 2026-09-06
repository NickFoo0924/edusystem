{{--
    LearnSync -- Blade view
    Module 1: Identity, Access & Digital Credentialing
    @author Serena Lim Sze Kee
--}}
{{-- notifications/preferences.blade.php --}}
@extends('layout')

@section('title', 'Notification preferences')

@section('content')

<a href="{{ route('notifications.index') }}" class="text-sm text-gray-500 hover:text-gray-800">
    &larr; Back to notifications
</a>

<h1 class="mt-6 text-2xl font-semibold tracking-tight">Notification preferences</h1>
<p class="mt-2 max-w-2xl text-sm text-gray-500">
    Switching a type off stops it being produced at all &mdash; the notification is never written, not
    merely hidden from you.
</p>

@include('partials.flash')

<form method="post" action="{{ route('notifications.preferences.update') }}"
      class="mt-6 max-w-xl rounded-xl border border-gray-200 bg-white p-8 shadow-sm">
    @csrf
    @method('PUT')

    <div class="space-y-4">
        @foreach ($types as $type => $label)
            <label class="flex items-start gap-3">
                <input type="checkbox" name="types[]" value="{{ $type }}"
                       @checked($current[$type] ?? true)
                       class="mt-1 rounded border-gray-300 text-blue-700 focus:ring-blue-500">
                <span>
                    <span class="block text-sm text-gray-800">{{ $label }}</span>
                    <code class="text-xs text-gray-400">{{ $type }}</code>
                </span>
            </label>
        @endforeach
    </div>

    <button type="submit"
            class="mt-8 rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
        Save preferences
    </button>
</form>

@endsection
