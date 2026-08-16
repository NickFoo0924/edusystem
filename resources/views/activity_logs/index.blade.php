{{-- activity_logs/index.blade.php --}}
@extends('layout')

@section('title', 'Activity Log')

@section('content')

<div class="flex flex-wrap items-start justify-between gap-4">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight">Activity Log</h1>
        <p class="mt-2 text-sm text-gray-500">
            Every security-relevant action, with actor, IP address and user agent. Rows are written
            once and never edited.
        </p>
    </div>
    <a href="{{ route('activity-logs.export', request()->query()) }}"
       class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
        Export CSV
    </a>
</div>

<form method="get" action="{{ route('activity-logs.index') }}"
      class="mt-6 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">

        <div>
            <label for="action" class="block text-xs font-medium uppercase tracking-wide text-gray-500">Action</label>
            <select id="action" name="action"
                    class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">All actions</option>
                @foreach ($actions as $action)
                    <option value="{{ $action }}" @selected($filters['action'] === $action)>{{ $action }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="user_id" class="block text-xs font-medium uppercase tracking-wide text-gray-500">Actor</label>
            <select id="user_id" name="user_id"
                    class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">Anyone</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected((string) $filters['user_id'] === (string) $user->id)>
                        {{ $user->name }} ({{ $user->role }})
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="from" class="block text-xs font-medium uppercase tracking-wide text-gray-500">From</label>
            <input id="from" name="from" type="date" value="{{ $filters['from'] }}"
                   class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
        </div>

        <div>
            <label for="to" class="block text-xs font-medium uppercase tracking-wide text-gray-500">To</label>
            <input id="to" name="to" type="date" value="{{ $filters['to'] }}"
                   class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
        </div>

        <div class="flex items-end gap-2">
            <button type="submit"
                    class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
                Filter
            </button>
            <a href="{{ route('activity-logs.index') }}"
               class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Reset
            </a>
        </div>

    </div>
</form>

<p class="mt-4 text-xs text-gray-500">{{ $logs->total() }} matching entries.</p>

<div class="mt-3 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-5 py-3">When</th>
                    <th class="px-5 py-3">Actor</th>
                    <th class="px-5 py-3">Action</th>
                    <th class="px-5 py-3">Target</th>
                    <th class="px-5 py-3">IP</th>
                    <th class="px-5 py-3">User agent</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($logs as $log)
                    <tr>
                        <td class="whitespace-nowrap px-5 py-3 text-gray-700">
                            {{ $log->created_at?->format('j M Y, H:i:s') }}
                        </td>
                        <td class="px-5 py-3">
                            @if ($log->user)
                                <span class="font-medium text-gray-900">{{ $log->user->name }}</span>
                                <span class="text-xs text-gray-500">({{ $log->user->role }})</span>
                            @else
                                <span class="text-gray-400">system</span>
                            @endif
                        </td>
                        <td class="px-5 py-3">
                            <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-700">{{ $log->action }}</code>
                        </td>
                        <td class="px-5 py-3 text-gray-600">{{ $log->targetLabel() ?? '—' }}</td>
                        <td class="whitespace-nowrap px-5 py-3 font-mono text-xs text-gray-600">{{ $log->ip_address }}</td>
                        <td class="max-w-xs truncate px-5 py-3 text-xs text-gray-500" title="{{ $log->user_agent }}">
                            {{ $log->user_agent }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-10 text-center text-gray-500">
                            No activity matches these filters.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-6">
    {{ $logs->links() }}
</div>

@endsection
