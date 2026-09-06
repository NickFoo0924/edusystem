{{--
    LearnSync -- Blade view
    Module 1: Identity, Access & Digital Credentialing
    @author Serena Lim Sze Kee
--}}
{{-- certificates/index.blade.php --}}
@extends('layout')

@section('title', 'My Certificates')

@section('content')

<h1 class="text-2xl font-semibold tracking-tight">My Certificates</h1>
<p class="mt-2 text-sm text-gray-500">
    Every credential issued to you, with a public link anyone can use to verify it.
</p>

@if ($certificates->isEmpty())

    <div class="mt-8 rounded-xl border border-dashed border-gray-300 bg-white p-10 text-center">
        <p class="text-sm text-gray-500">
            You have not earned any certificates yet. Complete a course to receive your first credential.
        </p>
    </div>

@else

    <div class="mt-8 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-6 py-3">Award</th>
                    <th class="px-6 py-3">Credential ID</th>
                    <th class="px-6 py-3">Score</th>
                    <th class="px-6 py-3">Issued</th>
                    <th class="px-6 py-3">Status</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($certificates as $certificate)
                    <tr>
                        <td class="px-6 py-4 font-medium text-gray-900">
                            {{ $certificate->course?->title ?? $certificate->learningPath?->title }}
                        </td>
                        <td class="px-6 py-4 font-mono text-xs tracking-wider text-gray-600">
                            {{ $certificate->credential_id }}
                        </td>
                        <td class="px-6 py-4 text-gray-700">
                            {{ rtrim(rtrim(number_format($certificate->final_score, 2), '0'), '.') }}%
                        </td>
                        <td class="px-6 py-4 text-gray-700">
                            {{ $certificate->issued_at->format('j M Y') }}
                        </td>
                        <td class="px-6 py-4">
                            @if ($certificate->revoked_at)
                                <span class="rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800">
                                    Revoked
                                </span>
                            @else
                                <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-800">
                                    Valid
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            <a href="{{ route('certificates.show', $certificate) }}"
                               class="font-medium text-blue-700 hover:text-blue-900">View</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

@endif

@endsection
