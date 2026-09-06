{{--
    LearnSync -- Blade view
    Module 1: Identity, Access & Digital Credentialing
    @author Serena Lim Sze Kee
--}}
{{-- certificates/show.blade.php --}}
@extends('layout')

@section('title', $certificate->credential_id)

@section('content')

<a href="{{ route('certificates.index') }}" class="text-sm text-gray-500 hover:text-gray-800">
    &larr; Back to my certificates
</a>

<div class="mt-6 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">

    <div class="border-b border-gray-200 px-8 py-6">
        <h1 class="text-xl font-semibold tracking-tight">
            {{ $certificate->course?->title ?? $certificate->learningPath?->title }}
        </h1>
        <p class="mt-1 font-mono text-sm tracking-wider text-gray-500">
            {{ $certificate->credential_id }}
        </p>
    </div>

    {{-- MODULE 1 CONSUMING MODULE 2's WEB SERVICE.

         The course code and lecturer below were fetched over HTTP from
         Module 2's getCourseInfo service, not read from Module 2's tables.
         The panel is absent when that service cannot be reached, which is
         why the credential itself still displays above without it. --}}
    @if ($courseInfo)
        <div class="border-b border-gray-200 bg-blue-50 px-8 py-4">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-blue-800">
                Course details, retrieved from Module 2 web service
            </p>
            <dl class="mt-2 grid grid-cols-1 gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                <div class="flex justify-between sm:block">
                    <dt class="text-gray-500">Course code</dt>
                    <dd class="font-medium text-gray-900">{{ $courseInfo['courseCode'] }}</dd>
                </div>
                <div class="flex justify-between sm:block">
                    <dt class="text-gray-500">Course title</dt>
                    <dd class="font-medium text-gray-900">{{ $courseInfo['courseTitle'] }}</dd>
                </div>
                @if (! empty($courseInfo['instructorName']))
                    <div class="flex justify-between sm:block">
                        <dt class="text-gray-500">Lecturer</dt>
                        <dd class="font-medium text-gray-900">{{ $courseInfo['instructorName'] }}</dd>
                    </div>
                @endif
                @if (! empty($courseInfo['studentCount']))
                    <div class="flex justify-between sm:block">
                        <dt class="text-gray-500">Students enrolled</dt>
                        <dd class="font-medium text-gray-900">{{ $courseInfo['studentCount'] }}</dd>
                    </div>
                @endif
            </dl>
        </div>
    @endif

    <dl class="divide-y divide-gray-100 px-8 text-sm">
        <div class="flex justify-between py-4">
            <dt class="text-gray-500">Status</dt>
            <dd>
                @if ($status === 'valid')
                    <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-800">Valid</span>
                @elseif ($status === 'revoked')
                    <span class="rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800">Revoked</span>
                @elseif ($status === 'expired')
                    <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800">Expired</span>
                @else
                    <span class="rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800">Tampered</span>
                @endif
            </dd>
        </div>
        <div class="flex justify-between py-4">
            <dt class="text-gray-500">Final score</dt>
            <dd class="font-medium text-gray-900">
                {{ rtrim(rtrim(number_format($certificate->final_score, 2), '0'), '.') }}%
            </dd>
        </div>
        <div class="flex justify-between py-4">
            <dt class="text-gray-500">Issued</dt>
            <dd class="font-medium text-gray-900">{{ $certificate->issued_at->format('j F Y') }}</dd>
        </div>
        @if ($certificate->revoked_at)
            <div class="flex justify-between py-4">
                <dt class="text-gray-500">Revocation reason</dt>
                <dd class="font-medium text-red-800">{{ $certificate->revocation_reason ?: 'not stated' }}</dd>
            </div>
        @endif
    </dl>

    <div class="border-t border-gray-200 bg-gray-50 px-8 py-6">
        <label for="verification-url" class="block text-xs font-medium uppercase tracking-wide text-gray-500">
            Public verification link
        </label>
        <input id="verification-url" type="text" readonly value="{{ $verificationUrl }}"
               onclick="this.select()"
               class="mt-2 w-full rounded-lg border-gray-300 bg-white font-mono text-xs text-gray-700 shadow-sm">
        <p class="mt-2 text-xs text-gray-500">
            Share this with an employer. They do not need a {{ config('app.name') }} account to check it.
        </p>

        <div class="mt-6 flex items-center gap-3">
            @if ($certificate->revoked_at)
                <span class="cursor-not-allowed rounded-lg bg-gray-200 px-4 py-2 text-sm font-medium text-gray-500">
                    Download disabled
                </span>
            @else
                <a href="{{ route('certificates.download', $certificate) }}"
                   class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
                    Download PDF
                </a>
            @endif

            <a href="{{ $verificationUrl }}" target="_blank"
               class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Open verification page
            </a>
        </div>
    </div>

</div>

@endsection
