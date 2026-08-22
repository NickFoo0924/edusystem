{{-- users/confirm.blade.php -- the interstitial that prevents accidental changes --}}
@extends('layout')

@section('title', $label)

@section('content')

<div class="mx-auto max-w-lg">

    <a href="{{ route('users.index') }}" class="text-sm text-gray-500 hover:text-gray-800">
        &larr; Back to accounts &mdash; nothing has changed yet
    </a>

    <div class="mt-6 overflow-hidden rounded-xl border {{ $destructive ? 'border-red-300' : 'border-gray-200' }} bg-white shadow-sm">

        <div class="border-b {{ $destructive ? 'border-red-200 bg-red-50' : 'border-gray-200 bg-gray-50' }} px-8 py-4">
            <h1 class="text-lg font-semibold {{ $destructive ? 'text-red-900' : 'text-gray-900' }}">
                {{ $label }}
            </h1>
        </div>

        <div class="px-8 py-6">

            {{-- Who this affects, spelled out so the wrong row cannot be acted on unnoticed. --}}
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <dt class="text-gray-500">Account</dt>
                    <dd class="font-medium text-gray-900">{{ $user->name }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">Email</dt>
                    <dd class="text-gray-900">{{ $user->email }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500">Current role</dt>
                    <dd class="capitalize text-gray-900">{{ $user->role }}</dd>
                </div>
                @if ($action === 'role')
                    <div class="flex justify-between border-t border-gray-100 pt-2">
                        <dt class="text-gray-500">New role</dt>
                        <dd class="font-semibold capitalize text-blue-800">{{ $newRole }}</dd>
                    </div>
                @endif
            </dl>

            <p class="mt-5 rounded-lg {{ $destructive ? 'bg-red-50 text-red-800' : 'bg-gray-50 text-gray-600' }} px-4 py-3 text-sm">
                {{ $consequence }}
            </p>

            @include('partials.flash')

            <form method="post" action="{{ route('users.perform', ['action' => $action, 'userId' => $user->id]) }}"
                  class="mt-6">
                @csrf

                @if ($action === 'role')
                    <input type="hidden" name="role" value="{{ $newRole }}">
                @endif

                <label for="confirm_password" class="block text-sm font-medium text-gray-700">
                    Enter your own password to confirm
                </label>
                <div class="relative mt-1">
                    <input id="confirm_password" name="confirm_password" type="password" required autofocus
                           autocomplete="current-password"
                           class="block w-full rounded-lg border-gray-300 pe-10 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <x-password-toggle for="confirm_password" />
                </div>
                <p class="mt-1 text-xs text-gray-500">
                    This is your password, not the account holder's. Nothing happens until it is correct.
                </p>

                <div class="mt-6 flex items-center gap-3">
                    <button type="submit"
                            class="rounded-lg px-4 py-2 text-sm font-medium text-white {{ $destructive ? 'bg-red-700 hover:bg-red-800' : 'bg-blue-700 hover:bg-blue-800' }}">
                        {{ $label }}
                    </button>
                    <a href="{{ route('users.index') }}"
                       class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Cancel
                    </a>
                </div>
            </form>

        </div>
    </div>

</div>

@endsection
