{{-- users/index.blade.php --}}
@extends('layout')

@section('title', 'Accounts')

@section('content')

<div class="flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight">Accounts</h1>
        <p class="mt-2 max-w-2xl text-sm text-gray-500">
            Every action here opens a confirmation page and requires your own password. Nothing on this
            screen changes an account directly, so a stray click is harmless.
        </p>
    </div>
    @can('invitation.issue')
        <a href="{{ route('invitations.create') }}"
           class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
            Invite someone
        </a>
    @endcan
</div>

@include('partials.flash')

<div class="mt-8 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-5 py-3">Name</th>
                    <th class="px-5 py-3">Role</th>
                    <th class="px-5 py-3">Status</th>
                    <th class="px-5 py-3">Last login</th>
                    <th class="px-5 py-3">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($users as $user)
                    @php $isSelf = $user->id === auth()->id(); @endphp
                    <tr class="{{ $user->trashed() ? 'bg-gray-50 opacity-60' : '' }}">

                        <td class="px-5 py-3">
                            <p class="font-medium text-gray-900">
                                {{ $user->name }}
                                @if ($isSelf)
                                    <span class="ml-1 rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-blue-800">you</span>
                                @endif
                            </p>
                            <p class="text-xs text-gray-500">{{ $user->email }}</p>
                        </td>

                        <td class="px-5 py-3">
                            @if ($user->trashed() || $isSelf)
                                <span class="capitalize text-gray-600">{{ $user->role }}</span>
                            @else
                                {{--
                                    A plain form with an explicit button. It deliberately does NOT
                                    submit when the dropdown changes: picking the wrong entry by
                                    accident must not do anything on its own.
                                --}}
                                <form method="get" action="{{ route('users.confirm', ['action' => 'role', 'userId' => $user->id]) }}"
                                      class="flex items-center gap-1.5">
                                    <select name="role"
                                            class="rounded-lg border-gray-300 py-1 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        @foreach (['admin', 'instructor', 'student'] as $role)
                                            <option value="{{ $role }}" @selected($user->role === $role)>{{ ucfirst($role) }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit"
                                            class="rounded border border-gray-300 px-2 py-1 text-xs font-medium text-gray-600 hover:bg-gray-50">
                                        Change
                                    </button>
                                </form>
                            @endif
                        </td>

                        <td class="px-5 py-3">
                            @if ($user->trashed())
                                <span class="rounded-full bg-gray-200 px-2.5 py-0.5 text-xs font-medium text-gray-600">Deleted</span>
                            @elseif (! $user->is_active)
                                <span class="rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800">Deactivated</span>
                            @elseif ($user->locked_until && $user->locked_until->isFuture())
                                <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800">Locked</span>
                            @else
                                <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-800">Active</span>
                            @endif
                            @if ($user->failed_login_attempts > 0)
                                <span class="ml-1 text-xs text-gray-400">{{ $user->failed_login_attempts }} failed</span>
                            @endif
                        </td>

                        <td class="px-5 py-3 text-xs text-gray-500">
                            {{ $user->last_login_at?->diffForHumans() ?? 'never' }}
                        </td>

                        <td class="px-5 py-3">
                            @if ($isSelf)
                                <span class="text-xs text-gray-400">your own account</span>
                            @else
                                {{--
                                    Links, not forms. Following one only opens a confirmation page,
                                    so nothing here can act on a mis-click.
                                --}}
                                <div class="flex flex-wrap items-center gap-3">
                                    @if ($user->trashed())
                                        @can('user.delete')
                                            <a href="{{ route('users.confirm', ['action' => 'restore', 'userId' => $user->id]) }}"
                                               class="text-xs font-medium text-blue-700 hover:text-blue-900">Restore</a>
                                        @endcan
                                    @else
                                        <a href="{{ route('users.confirm', ['action' => $user->is_active ? 'deactivate' : 'activate', 'userId' => $user->id]) }}"
                                           class="text-xs font-medium {{ $user->is_active ? 'text-gray-700 hover:text-gray-900' : 'text-emerald-700 hover:text-emerald-900' }}">
                                            {{ $user->is_active ? 'Deactivate' : 'Activate' }}
                                        </a>

                                        @can('user.unlock')
                                            @if ($user->locked_until || $user->failed_login_attempts > 0)
                                                <a href="{{ route('users.confirm', ['action' => 'unlock', 'userId' => $user->id]) }}"
                                                   class="text-xs font-medium text-amber-700 hover:text-amber-900">Unlock</a>
                                            @endif
                                        @endcan

                                        @can('user.delete')
                                            <a href="{{ route('users.confirm', ['action' => 'delete', 'userId' => $user->id]) }}"
                                               class="text-xs font-medium text-red-700 hover:text-red-900">Delete</a>
                                        @endcan
                                    @endif
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@endsection
