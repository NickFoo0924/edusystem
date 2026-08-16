{{-- permissions/index.blade.php --}}
@extends('layout')

@section('title', 'Permission Matrix')

@section('content')

<h1 class="text-2xl font-semibold tracking-tight">Permission Matrix</h1>
<p class="mt-2 max-w-2xl text-sm text-gray-500">
    Every grant below is a row in the database, not a line of code. Ticking a box changes what
    <code class="rounded bg-gray-100 px-1 text-xs">$user-&gt;can(...)</code> returns across the whole
    application immediately.
</p>

@if (session('success'))
    <div class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
        {{ session('success') }}
    </div>
@endif

<form method="post" action="{{ route('permissions.update') }}" class="mt-8">
    @csrf
    @method('PUT')

    @foreach ($groupedPermissions as $group => $permissions)

        <div class="mb-6 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">

            <div class="border-b border-gray-200 bg-gray-50 px-6 py-3">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-600">{{ $group }}</h2>
            </div>

            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wide text-gray-500">
                        <th class="px-6 py-3 font-medium">Permission</th>
                        @foreach ($roles as $role)
                            <th class="w-28 px-6 py-3 text-center font-medium">{{ $role }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($permissions as $permission)
                        @php
                            $held = $permission->permissionRoles->pluck('role')->all();
                        @endphp
                        <tr>
                            <td class="px-6 py-3">
                                <div class="font-medium text-gray-900">{{ $permission->label }}</div>
                                <code class="text-xs text-gray-500">{{ $permission->key }}</code>
                            </td>

                            @foreach ($roles as $role)
                                @php
                                    // The one grant that cannot be revoked, or an
                                    // admin could lock everyone out of this screen.
                                    $locked = $permission->key === $protectedKey && $role === $protectedRole;
                                @endphp
                                <td class="px-6 py-3 text-center">
                                    <input type="checkbox"
                                           name="grants[{{ $permission->id }}][]"
                                           value="{{ $role }}"
                                           @checked(in_array($role, $held, true))
                                           @disabled($locked)
                                           class="h-4 w-4 rounded border-gray-300 text-blue-700 focus:ring-blue-500 {{ $locked ? 'cursor-not-allowed opacity-60' : '' }}">

                                    @if ($locked)
                                        {{-- Disabled inputs are not submitted, so post it anyway. --}}
                                        <input type="hidden" name="grants[{{ $permission->id }}][]" value="{{ $role }}">
                                        <div class="mt-1 text-[10px] uppercase tracking-wide text-gray-400">locked</div>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>

        </div>

    @endforeach

    <div class="sticky bottom-0 flex items-center gap-3 border-t border-gray-200 bg-gray-100/95 py-4">
        <button type="submit"
                class="rounded-lg bg-blue-700 px-5 py-2 text-sm font-medium text-white hover:bg-blue-800">
            Save matrix
        </button>
        <span class="text-xs text-gray-500">
            Changes apply on the next request &mdash; no deployment, no code change.
        </span>
    </div>

</form>

@endsection
