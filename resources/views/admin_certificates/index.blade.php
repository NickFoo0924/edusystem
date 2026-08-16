{{-- admin_certificates/index.blade.php --}}
@extends('layout')

@section('title', 'Credential Register')

@section('content')

<div class="flex flex-wrap items-start justify-between gap-4">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight">Credential Register</h1>
        <p class="mt-2 max-w-2xl text-sm text-gray-500">
            Every credential the system has issued. Contents can never be edited &mdash; that is what keeps
            the integrity hash meaningful. A credential can only be revoked.
        </p>
    </div>
    @can('certificate.issue')
        <a href="{{ route('admin.certificates.create') }}"
           class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
            Issue manually
        </a>
    @endcan
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
@if ($errors->any())
    <div class="mt-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        <ul class="list-inside list-disc space-y-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="get" action="{{ route('admin.certificates.index') }}"
      class="mt-6 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <label for="status" class="block text-xs font-medium uppercase tracking-wide text-gray-500">Status</label>
            <select id="status" name="status"
                    class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">All</option>
                @foreach (['valid' => 'Valid', 'revoked' => 'Revoked', 'expired' => 'Expired'] as $value => $label)
                    <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="student_id" class="block text-xs font-medium uppercase tracking-wide text-gray-500">Holder</label>
            <select id="student_id" name="student_id"
                    class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">Anyone</option>
                @foreach ($students as $student)
                    <option value="{{ $student->id }}" @selected((string) $filters['student_id'] === (string) $student->id)>
                        {{ $student->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="credential_id" class="block text-xs font-medium uppercase tracking-wide text-gray-500">
                Credential ID
            </label>
            <input id="credential_id" name="credential_id" type="text" value="{{ $filters['credential_id'] }}"
                   placeholder="LS-2026-…"
                   class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
        </div>
        <div class="flex items-end gap-2">
            <button type="submit"
                    class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
                Filter
            </button>
            <a href="{{ route('admin.certificates.index') }}"
               class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Reset
            </a>
        </div>
    </div>
</form>

<p class="mt-4 text-xs text-gray-500">{{ $certificates->total() }} credentials.</p>

<div class="mt-3 space-y-3">

    @forelse ($certificates as $certificate)
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">

            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-mono text-sm tracking-wider text-gray-800">
                            {{ $certificate->credential_id }}
                        </span>
                        @if ($certificate->revoked_at)
                            <span class="rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-red-800">Revoked</span>
                        @elseif ($certificate->expires_at && $certificate->expires_at->isPast())
                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-amber-800">Expired</span>
                        @else
                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-emerald-800">Valid</span>
                        @endif
                        @if ($certificate->learning_path_id)
                            <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-indigo-800">Pathway</span>
                        @endif
                    </div>

                    <p class="mt-1 text-sm text-gray-900">
                        <span class="font-medium">{{ $certificate->student->name }}</span>
                        &mdash; {{ $certificate->course?->title ?? $certificate->learningPath?->title }}
                    </p>
                    <p class="mt-0.5 text-xs text-gray-500">
                        {{ rtrim(rtrim(number_format($certificate->final_score, 2), '0'), '.') }}%
                        &middot; issued {{ $certificate->issued_at->format('j M Y') }}
                        @if ($certificate->revoked_at)
                            &middot; revoked {{ $certificate->revoked_at->format('j M Y') }}
                        @endif
                    </p>

                    @if ($certificate->revoked_at)
                        <p class="mt-2 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-800">
                            Reason: {{ $certificate->revocation_reason }}
                        </p>
                    @endif
                </div>

                <a href="{{ route('certificates.verify', ['credential_id' => $certificate->credential_id]) }}"
                   target="_blank"
                   class="whitespace-nowrap text-sm font-medium text-blue-700 hover:text-blue-900">
                    Public page &rarr;
                </a>
            </div>

            @can('certificate.revoke')
                @unless ($certificate->revoked_at)
                    <form method="post" action="{{ route('admin.certificates.revoke', $certificate) }}"
                          onsubmit="return confirm('Revoke {{ $certificate->credential_id }}? This is permanent and immediately public.');"
                          class="mt-4 flex flex-wrap items-end gap-3 border-t border-gray-100 pt-4">
                        @csrf
                        @method('PATCH')
                        <div class="min-w-0 flex-1">
                            <label for="reason-{{ $certificate->id }}" class="block text-xs font-medium uppercase tracking-wide text-gray-500">
                                Revocation reason (shown publicly)
                            </label>
                            <input id="reason-{{ $certificate->id }}" name="revocation_reason" type="text"
                                   minlength="10" maxlength="255"
                                   placeholder="Issued against an incorrect final grade."
                                   class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500">
                        </div>
                        <button type="submit"
                                class="rounded-lg bg-red-700 px-4 py-2 text-sm font-medium text-white hover:bg-red-800">
                            Revoke
                        </button>
                    </form>
                @endunless
            @endcan

        </div>
    @empty
        <div class="rounded-xl border border-dashed border-gray-300 bg-white p-10 text-center">
            <p class="text-sm text-gray-500">No credentials match these filters.</p>
        </div>
    @endforelse

</div>

<div class="mt-6">
    {{ $certificates->links() }}
</div>

@endsection
