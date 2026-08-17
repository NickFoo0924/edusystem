{{-- profile/edit.blade.php --}}
@extends('layout')

@section('title', 'Profile')

@section('content')

<h1 class="text-2xl font-semibold tracking-tight">Profile</h1>
<p class="mt-2 text-sm text-gray-500">Your display picture, contact details and password.</p>

@include('partials.flash')

<div class="mt-6 max-w-xl space-y-6">
    <div class="rounded-xl border border-gray-200 bg-white p-8 shadow-sm">
        @include('profile.partials.update-profile-information-form')
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-8 shadow-sm">
        @include('profile.partials.update-password-form')
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-8 shadow-sm">
        @include('profile.partials.delete-user-form')
    </div>
</div>

@endsection
