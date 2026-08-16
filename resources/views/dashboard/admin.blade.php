{{-- dashboard/admin.blade.php --}}
@extends('layout')

@section('title', 'Dashboard')

@section('content')

<h1 class="text-2xl font-semibold tracking-tight">System overview</h1>
<p class="mt-2 text-sm text-gray-500">Hello, {{ auth()->user()->name }}.</p>

@include('partials.flash')

<div class="mt-8 grid grid-cols-2 gap-4 lg:grid-cols-3">
    @foreach ([
        ['Accounts', $userCount, $activeCount.' active'],
        ['Locked out', $lockedCount, $lockedCount > 0 ? 'needs attention' : 'none'],
        ['Courses', $courseCount, ''],
        ['Credentials issued', $certificateCount, ''],
        ['Revoked', $revokedCount, ''],
    ] as [$label, $value, $note])
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p class="text-xs uppercase tracking-wide text-gray-500">{{ $label }}</p>
            <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $value }}</p>
            @if ($note)
                <p class="mt-1 text-xs text-gray-400">{{ $note }}</p>
            @endif
        </div>
    @endforeach
</div>

<section class="mt-8 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
    <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-6 py-3">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-600">Recent activity</h2>
        @can('activitylog.view')
            <a href="{{ route('activity-logs.index') }}" class="text-sm font-medium text-blue-700 hover:text-blue-900">
                Full log
            </a>
        @endcan
    </div>
    <ul class="divide-y divide-gray-100">
        @forelse ($recentActivity as $log)
            <li class="flex items-center justify-between px-6 py-3 text-sm">
                <span>
                    <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-700">{{ $log->action }}</code>
                    <span class="ml-2 text-gray-600">{{ $log->user->name ?? 'system' }}</span>
                </span>
                <span class="text-xs text-gray-400">{{ $log->created_at?->diffForHumans() }}</span>
            </li>
        @empty
            <li class="px-6 py-10 text-center text-sm text-gray-500">No activity recorded.</li>
        @endforelse
    </ul>
</section>

@endsection
