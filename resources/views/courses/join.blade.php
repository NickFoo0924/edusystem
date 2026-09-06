{{--
    LearnSync -- Blade view
    Module 2: Academic Resources Repository
    @author Foo Chong Xian
--}}
{{-- courses/join.blade.php --}}
@extends('layout')

@section('title', 'Join a course')

@section('content')

<h1 class="text-2xl font-semibold tracking-tight">Join a course</h1>
<p class="mt-2 max-w-2xl text-sm text-gray-500">
    Courses are not browsable. You get into one either by accepting an invitation from the lecturer,
    or by entering the class code they gave you here.
</p>

@include('partials.flash')

<div class="mt-6 max-w-xl space-y-6">

    <div class="rounded-xl border border-gray-200 bg-white p-8 shadow-sm">
        <h2 class="text-lg font-medium text-gray-900">You are signed in as</h2>

        <div class="mt-4 flex items-center gap-3 rounded-lg bg-gray-50 p-4">
            <x-avatar :user="auth()->user()" size="sm" />
            <div class="min-w-0">
                <p class="truncate text-sm font-medium text-gray-900">{{ auth()->user()->name }}</p>
                <p class="truncate text-sm text-gray-500">{{ auth()->user()->email }}</p>
            </div>
        </div>

        <form method="post" action="{{ route('courses.join.store') }}" class="mt-6">
            @csrf

            <label for="class_code" class="block text-sm font-medium text-gray-700">Class code</label>
            <p class="mt-1 text-sm text-gray-500">
                Ask your lecturer for the class code, then enter it here.
            </p>

            <input type="text" name="class_code" id="class_code" required autofocus
                   value="{{ old('class_code') }}"
                   autocapitalize="off" autocomplete="off" spellcheck="false" maxlength="8"
                   placeholder="Class code"
                   class="mt-3 block w-full max-w-xs rounded-lg border-gray-300 font-mono tracking-widest shadow-sm focus:border-blue-500 focus:ring-blue-500">

            <x-input-error :messages="$errors->get('class_code')" class="mt-2" />

            <button type="submit"
                    class="mt-5 rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
                Join
            </button>
        </form>
    </div>

    <div class="rounded-xl border border-dashed border-gray-300 bg-white p-6">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">To join with a class code</h2>
        <ul class="mt-3 list-disc space-y-1.5 pl-5 text-sm text-gray-600">
            <li>Use 6 letters or numbers, with no spaces or symbols</li>
            <li>Capitalisation does not matter</li>
            <li>A code stops working if the lecturer issues a new one</li>
            <li>If you were invited instead, the course is waiting on your
                <a href="{{ route('courses.index') }}" class="font-medium text-blue-700 hover:text-blue-900">Courses</a>
                page &mdash; no code needed</li>
        </ul>
    </div>

</div>

@endsection
