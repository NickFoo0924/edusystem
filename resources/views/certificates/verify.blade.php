{{-- certificates/verify.blade.php --}}
@extends('layout')

@section('title', 'Verify credential '.$credentialId)

@section('content')

@php
    // Presentation only. The status itself was decided by the
    // CredentialAuthority Facade, not here.
    $badge = [
        'valid'     => ['label' => 'VALID',      'ring' => 'bg-emerald-50 border-emerald-200', 'pill' => 'bg-emerald-600'],
        'revoked'   => ['label' => 'REVOKED',    'ring' => 'bg-red-50 border-red-200',         'pill' => 'bg-red-600'],
        'expired'   => ['label' => 'EXPIRED',    'ring' => 'bg-amber-50 border-amber-200',     'pill' => 'bg-amber-600'],
        'tampered'  => ['label' => 'TAMPERED',   'ring' => 'bg-red-50 border-red-200',         'pill' => 'bg-red-700'],
        'not_found' => ['label' => 'NOT FOUND',  'ring' => 'bg-gray-50 border-gray-200',       'pill' => 'bg-gray-500'],
    ][$status];
@endphp

<div class="mx-auto max-w-2xl">

    <h1 class="text-center text-2xl font-semibold tracking-tight">Credential Verification</h1>
    <p class="mt-2 text-center text-sm text-gray-500">
        Checked against the issuing records of {{ config('app.name') }}.
    </p>

    <div class="mt-8 rounded-xl border {{ $badge['ring'] }} p-8 shadow-sm">

        <div class="flex flex-col items-center">
            <span class="rounded-full {{ $badge['pill'] }} px-4 py-1 text-sm font-semibold tracking-wide text-white">
                {{ $badge['label'] }}
            </span>
            <p class="mt-4 font-mono text-lg tracking-wider text-gray-800">{{ $credentialId }}</p>
        </div>

        @if ($status === 'not_found')

            <p class="mt-6 text-center text-sm text-gray-600">
                No credential with this ID has ever been issued. Check the ID for typing errors,
                or treat the certificate as unverified.
            </p>

        @else

            @if ($status === 'revoked')
                <div class="mt-6 rounded-lg bg-white/70 p-4 text-sm">
                    <p class="font-medium text-red-800">This credential was withdrawn by an administrator.</p>
                    <p class="mt-1 text-gray-700">
                        Reason: {{ $certificate->revocation_reason ?: 'not stated' }}
                        &middot; Revoked {{ $certificate->revoked_at->format('j F Y') }}
                    </p>
                </div>
            @endif

            @if ($status === 'tampered')
                <div class="mt-6 rounded-lg bg-white/70 p-4 text-sm">
                    <p class="font-medium text-red-800">Integrity check failed.</p>
                    <p class="mt-1 text-gray-700">
                        The stored record no longer matches the hash recorded when this credential was
                        issued, so its details cannot be trusted.
                    </p>
                </div>
            @endif

            @if ($status === 'expired')
                <div class="mt-6 rounded-lg bg-white/70 p-4 text-sm">
                    <p class="font-medium text-amber-800">
                        This credential expired on {{ $certificate->expires_at->format('j F Y') }}.
                    </p>
                </div>
            @endif

            <dl class="mt-8 divide-y divide-gray-200 border-t border-gray-200 text-sm">
                <div class="flex justify-between py-3">
                    <dt class="text-gray-500">Holder</dt>
                    <dd class="font-medium text-gray-900">{{ $certificate->student->name }}</dd>
                </div>
                <div class="flex justify-between py-3">
                    <dt class="text-gray-500">
                        {{ $certificate->learning_path_id ? 'Learning path' : 'Course' }}
                    </dt>
                    <dd class="font-medium text-gray-900">
                        {{ $certificate->course?->title ?? $certificate->learningPath?->title ?? '&mdash;' }}
                    </dd>
                </div>
                <div class="flex justify-between py-3">
                    <dt class="text-gray-500">Final score</dt>
                    <dd class="font-medium text-gray-900">{{ rtrim(rtrim(number_format($certificate->final_score, 2), '0'), '.') }}%</dd>
                </div>
                <div class="flex justify-between py-3">
                    <dt class="text-gray-500">Issued</dt>
                    <dd class="font-medium text-gray-900">{{ $certificate->issued_at->format('j F Y') }}</dd>
                </div>
            </dl>

            @if ($status === 'valid')
                <p class="mt-6 text-center text-xs text-emerald-800">
                    Integrity hash verified &mdash; this record has not been altered since issuance.
                </p>
            @endif

        @endif

    </div>

    <p class="mt-6 text-center text-xs text-gray-400">
        This page discloses only the holder's name, the award, the score and the status.
        No other personal information is published.
    </p>

</div>

@endsection
