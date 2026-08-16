{{-- badges/index.blade.php --}}
@extends('layout')

@section('title', 'Manage Badges')

@section('content')

<div class="flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight">Badge Rules</h1>
        <p class="mt-2 text-sm text-gray-500">
            Criteria are stored as data. The rules engine evaluates whatever is active here.
        </p>
    </div>
    <a href="{{ route('badges.create') }}"
       class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
        New badge
    </a>
</div>

@if (session('success'))
    <div class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
        {{ session('success') }}
    </div>
@endif

<div class="mt-8 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
    <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
            <tr>
                <th class="px-6 py-3">Badge</th>
                <th class="px-6 py-3">Tier</th>
                <th class="px-6 py-3">Criteria</th>
                <th class="px-6 py-3">Awarded</th>
                <th class="px-6 py-3">Active</th>
                <th class="px-6 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($badges as $badge)
                <tr>
                    <td class="px-6 py-4">
                        <div class="font-medium text-gray-900">{{ $badge->name }}</div>
                        <div class="text-xs text-gray-500">{{ $badge->description }}</div>
                    </td>
                    <td class="px-6 py-4 capitalize text-gray-700">{{ $badge->tier }}</td>
                    <td class="px-6 py-4">
                        <code class="text-xs text-gray-600">{{ $badge->criteria_type }}</code>
                        <span class="text-gray-400">&ge;</span>
                        <span class="font-medium text-gray-900">{{ $badge->criteria_value }}</span>
                    </td>
                    <td class="px-6 py-4 text-gray-700">{{ $badge->students_count }}</td>
                    <td class="px-6 py-4">
                        @if ($badge->is_active)
                            <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-800">Active</span>
                        @else
                            <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-500">Inactive</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex items-center justify-end gap-3">
                            <a href="{{ route('badges.edit', $badge) }}"
                               class="font-medium text-blue-700 hover:text-blue-900">Edit</a>
                            <form method="post" action="{{ route('badges.destroy', $badge) }}"
                                  onsubmit="return confirm('Delete this badge? Every award of it disappears from students\' cabinets.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="font-medium text-red-700 hover:text-red-900">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-6 py-10 text-center text-gray-500">
                        No badge rules defined yet.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@endsection
