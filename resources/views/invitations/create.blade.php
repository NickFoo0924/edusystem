{{--
    LearnSync -- Blade view
    Module 1: Identity, Access & Digital Credentialing
    @author Serena Lim Sze Kee
--}}
{{-- invitations/create.blade.php --}}
@extends('layout')

@section('title', 'Invite a user')

@section('content')

<a href="{{ route('invitations.index') }}" class="text-sm text-gray-500 hover:text-gray-800">
    &larr; Back to invitations
</a>

<h1 class="mt-6 text-2xl font-semibold tracking-tight">Invite a user</h1>

<form method="post" action="{{ route('invitations.store') }}"
      class="mt-6 max-w-xl rounded-xl border border-gray-200 bg-white p-8 shadow-sm">
    @csrf

    @if ($errors->any())
        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            <ul class="list-inside list-disc space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="space-y-5">

        <div>
            <label for="email" class="block text-sm font-medium text-gray-700">Email address</label>
            <input id="email" name="email" type="email" required value="{{ old('email') }}"
                   class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
        </div>

        <div>
            <label for="role" class="block text-sm font-medium text-gray-700">Register them as</label>
            <select id="role" name="role"
                    class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                @foreach ($roles as $role)
                    <option value="{{ $role }}" @selected(old('role') === $role)>{{ ucfirst($role) }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-gray-500">
                The recipient cannot change this. Their role is fixed by the invitation.
            </p>
        </div>

        <div>
            <label for="expires_in_days" class="block text-sm font-medium text-gray-700">Link valid for</label>
            <div class="mt-1 flex items-center gap-2">
                <input id="expires_in_days" name="expires_in_days" type="number" min="1" max="90" required
                       value="{{ old('expires_in_days', $defaultDays) }}"
                       class="block w-28 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <span class="text-sm text-gray-600">days</span>
            </div>
        </div>

    </div>

    <div class="mt-8 flex items-center gap-3">
        <button type="submit"
                class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
            Send invitation
        </button>
        <a href="{{ route('invitations.index') }}"
           class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
            Cancel
        </a>
    </div>

</form>

{{-- [STRETCH] Bulk import from a class list. --}}
<form method="post" action="{{ route('invitations.bulk') }}" enctype="multipart/form-data"
      class="mt-6 max-w-xl rounded-xl border border-gray-200 bg-white p-8 shadow-sm">
    @csrf

    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-600">Or import a class list</h2>
    <p class="mt-2 text-xs text-gray-500">
        A CSV with one email address per line, in the first column. A header row is ignored.
        Addresses that already have an account are reported back, not silently skipped.
    </p>

    <div class="mt-5 space-y-4">
        <div>
            <label for="csv" class="block text-sm font-medium text-gray-700">CSV file</label>
            <input id="csv" name="csv" type="file" accept=".csv,text/csv" required
                   class="mt-1 block w-full text-sm text-gray-600 file:mr-4 file:rounded-lg file:border-0 file:bg-gray-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-gray-700 hover:file:bg-gray-200">
        </div>

        <div>
            <label for="bulk_role" class="block text-sm font-medium text-gray-700">Register them all as</label>
            <select id="bulk_role" name="role"
                    class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                @foreach ($roles as $role)
                    <option value="{{ $role }}" @selected($role === 'student')>{{ ucfirst($role) }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <button type="submit"
            class="mt-6 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
        Import and invite
    </button>
</form>

@endsection
