{{--
    LearnSync -- Blade view
    Module 1: Identity, Access & Digital Credentialing
    @author Serena Lim Sze Kee
--}}
{{-- notifications/index.blade.php --}}
@extends('layout')

@section('title', 'Notifications')

@section('content')

<div class="flex flex-wrap items-center justify-between gap-3">
    <h1 class="text-2xl font-semibold tracking-tight">Notifications</h1>
    <div class="flex items-center gap-3">
        @can('notification.preferences')
            <a href="{{ route('notifications.preferences.edit') }}"
               class="text-sm font-medium text-gray-600 hover:text-gray-900">Preferences</a>
        @endcan
        <form method="post" action="{{ route('notifications.readAll') }}">
            @csrf
            <button type="submit"
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Mark all read
            </button>
        </form>
    </div>
</div>

@include('partials.flash')

<div class="mt-8 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
    <ul class="divide-y divide-gray-100">
        @forelse ($notifications as $notification)
            <li class="flex items-start gap-4 px-6 py-4 {{ $notification->is_read ? '' : 'bg-blue-50/50' }}">
                <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $notification->is_read ? 'bg-transparent' : 'bg-blue-600' }}"></span>

                <div class="min-w-0 flex-1">
                    <p class="text-sm {{ $notification->is_read ? 'text-gray-600' : 'font-medium text-gray-900' }}">
                        {{ $notification->message }}
                    </p>
                    <p class="mt-0.5 text-xs text-gray-400">
                        <code>{{ $notification->type }}</code> &middot; {{ $notification->created_at->diffForHumans() }}
                    </p>
                </div>

                <div class="flex shrink-0 items-center gap-3">
                    @if ($notification->link)
                        <form method="post" action="{{ route('notifications.read', $notification) }}">
                            @csrf
                            <button type="submit" class="text-sm font-medium text-blue-700 hover:text-blue-900">Open</button>
                        </form>
                    @endif
                    <form method="post" action="{{ route('notifications.destroy', $notification) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-sm text-gray-400 hover:text-red-700">Dismiss</button>
                    </form>
                </div>
            </li>
        @empty
            <li class="px-6 py-12 text-center text-sm text-gray-500">Nothing here yet.</li>
        @endforelse
    </ul>
</div>

<div class="mt-6">{{ $notifications->links() }}</div>

@endsection
