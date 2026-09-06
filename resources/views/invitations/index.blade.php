{{--
    LearnSync -- Blade view
    Module 1: Identity, Access & Digital Credentialing
    @author Serena Lim Sze Kee
--}}
{{-- invitations/index.blade.php --}}
@extends('layout')

@section('title', 'Invitations')

@section('content')

<div class="flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight">Invitations</h1>
        <p class="mt-2 text-sm text-gray-500">
            There is no public sign-up. Every account on {{ config('app.name') }} begins as an
            invitation issued here.
        </p>
    </div>
    <a href="{{ route('invitations.create') }}"
       class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
        Invite someone
    </a>
</div>

@if (session('success'))
    <div class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
        {{ session('success') }}
    </div>
@endif

@if (session('error'))
    <div class="mt-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        {{ session('error') }}
    </div>
@endif

<div class="mt-8 space-y-4">

    @forelse ($invitations as $invitation)
        @php
            $status = $invitation->status();
            $chip = [
                'pending'  => 'bg-blue-100 text-blue-800',
                'accepted' => 'bg-emerald-100 text-emerald-800',
                'expired'  => 'bg-gray-100 text-gray-500',
            ][$status];
        @endphp

        <div class="rounded-xl border bg-white p-6 shadow-sm {{ session('highlight') === $invitation->id ? 'border-blue-400 ring-2 ring-blue-100' : 'border-gray-200' }}">

            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="font-medium text-gray-900">{{ $invitation->email }}</span>
                        <span class="rounded-full px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide {{ $chip }}">
                            {{ $status }}
                        </span>
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-gray-600">
                            {{ $invitation->role }}
                        </span>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">
                        Invited by {{ $invitation->inviter->name }}
                        @if ($status === 'accepted')
                            &middot; accepted {{ $invitation->accepted_at->format('j M Y, g:ia') }}
                        @else
                            &middot; expires {{ $invitation->expires_at->format('j M Y, g:ia') }}
                        @endif
                    </p>
                </div>

                @if ($status !== 'accepted')
                    <form method="post" action="{{ route('invitations.destroy', $invitation) }}"
                          onsubmit="return confirm('Withdraw this invitation? The link stops working immediately.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-sm font-medium text-red-700 hover:text-red-900">
                            Withdraw
                        </button>
                    </form>
                @endif
            </div>

            @if ($status === 'pending')
                <div class="mt-4">
                    <label class="block text-xs font-medium uppercase tracking-wide text-gray-500">
                        Registration link
                    </label>
                    <input type="text" readonly value="{{ $invitation->registrationUrl() }}"
                           onclick="this.select()"
                           class="mt-1 w-full rounded-lg border-gray-300 bg-gray-50 font-mono text-xs text-gray-700 shadow-sm">
                    <p class="mt-1 text-xs text-gray-500">
                        Emailed to the recipient. Copy it here if you would rather send it yourself.
                    </p>
                </div>
            @endif

        </div>
    @empty
        <div class="rounded-xl border border-dashed border-gray-300 bg-white p-10 text-center">
            <p class="text-sm text-gray-500">No invitations issued yet.</p>
        </div>
    @endforelse

</div>

@endsection
