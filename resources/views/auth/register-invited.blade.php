{{-- auth/register-invited.blade.php --}}
@extends('layout')

@section('title', 'Accept your invitation')

@section('content')

<div class="mx-auto max-w-md">

    <h1 class="text-center text-2xl font-semibold tracking-tight">Accept your invitation</h1>
    <p class="mt-2 text-center text-sm text-gray-500">
        You have been invited to join {{ config('app.name') }} as
        <span class="font-medium text-gray-700">{{ $invitation->role }}</span>.
    </p>

    <form method="post" action="{{ route('register.invited', ['token' => $invitation->token]) }}"
          class="mt-8 rounded-xl border border-gray-200 bg-white p-8 shadow-sm">
        @csrf

        @if ($errors->any())
            <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="space-y-5">

            <div>
                <label class="block text-sm font-medium text-gray-700">Email address</label>
                {{-- Fixed by the invitation, shown so the recipient can see which
                     address the account will belong to. Never submitted. --}}
                <input type="email" value="{{ $invitation->email }}" disabled
                       class="mt-1 block w-full cursor-not-allowed rounded-lg border-gray-200 bg-gray-50 text-gray-500 shadow-sm">
                <p class="mt-1 text-xs text-gray-500">Set by your invitation and cannot be changed.</p>
            </div>

            <div>
                <label for="name" class="block text-sm font-medium text-gray-700">Full name</label>
                <input id="name" name="name" type="text" required autofocus value="{{ old('name') }}"
                       class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                <div class="relative mt-1">
                    <input id="password" name="password" type="password" required autocomplete="new-password"
                           class="block w-full rounded-lg border-gray-300 pe-10 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <x-password-toggle for="password" />
                </div>
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-gray-700">
                    Confirm password
                </label>
                <div class="relative mt-1">
                    <input id="password_confirmation" name="password_confirmation" type="password" required
                           autocomplete="new-password"
                           class="block w-full rounded-lg border-gray-300 pe-10 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <x-password-toggle for="password_confirmation" />
                </div>
            </div>

        </div>

        <button type="submit"
                class="mt-8 w-full rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-800">
            Create my account
        </button>

        <p class="mt-4 text-center text-xs text-gray-500">
            This link expires {{ $invitation->expires_at->diffForHumans() }}.
        </p>

    </form>

</div>

@endsection
