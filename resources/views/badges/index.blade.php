{{-- badges/index.blade.php --}}
@extends('layout')

@section('title', 'Award Rules')

@section('content')

<div class="flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight">Award Rules</h1>
        <p class="mt-2 text-sm text-gray-500">
            Badge and certificate rules alike. Conditions are stored as data, not code &mdash; the rules
            engine evaluates whatever is active here, through one path for both kinds of award.
        </p>
    </div>
    <a href="{{ route('badges.create') }}"
       class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
        New rule
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
                <th class="px-6 py-3">Rule</th>
                <th class="px-6 py-3">Awards</th>
                <th class="px-6 py-3">Tier</th>
                <th class="px-6 py-3">Condition</th>
                <th class="px-6 py-3">Held by</th>
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
                    <td class="px-6 py-4">
                        @if ($badge->isCertificateRule())
                            <span class="rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-medium text-indigo-800">
                                Certificate
                            </span>
                            <div class="mt-1 text-xs text-gray-500">
                                {{ $badge->certificateTemplate?->name ?? 'Default template' }}
                            </div>
                        @else
                            <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700">
                                Badge
                            </span>
                        @endif
                    </td>
                    <td class="px-6 py-4 capitalize text-gray-700">
                        {{ $badge->isCertificateRule() ? '—' : $badge->tier }}
                    </td>
                    <td class="px-6 py-4">
                        <span class="text-gray-700">{{ $badge->criteriaDescription() }}</span>
                    </td>
                    <td class="px-6 py-4 text-gray-700">
                        {{ $badge->isCertificateRule() ? '—' : $badge->students_count }}
                    </td>
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
                            {{-- Deactivating is the non-destructive way to stop a
                                 rule: the engine skips it, and every award already
                                 made from it stays where it is. --}}
                            <form method="post" action="{{ route('badges.toggle', $badge) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="font-medium text-gray-600 hover:text-gray-900">
                                    {{ $badge->is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                            </form>
                            <form method="post" action="{{ route('badges.destroy', $badge) }}"
                                  onsubmit="return confirm('Delete this rule? For a badge rule, every award of it disappears from students\' cabinets. Deactivate instead to stop it without touching awards already made.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="font-medium text-red-700 hover:text-red-900">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-6 py-10 text-center text-gray-500">
                        No award rules defined yet.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@endsection
