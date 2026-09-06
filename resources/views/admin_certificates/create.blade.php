{{--
    LearnSync -- Blade view
    Module 1: Identity, Access & Digital Credentialing
    @author Serena Lim Sze Kee
--}}
{{-- admin_certificates/create.blade.php --}}
@extends('layout')

@section('title', 'Issue a credential')

@section('content')

<a href="{{ route('admin.certificates.index') }}" class="text-sm text-gray-500 hover:text-gray-800">
    &larr; Back to the register
</a>

<h1 class="mt-6 text-2xl font-semibold tracking-tight">Issue a credential manually</h1>
<p class="mt-2 max-w-2xl text-sm text-gray-500">
    Credentials are normally minted automatically when a student meets a course's completion criteria.
    This is the exception route, and it goes through the same authority: same ID format, same integrity
    hash, same PDF, same audit entry.
</p>

<form method="post" action="{{ route('admin.certificates.store') }}"
      class="mt-6 max-w-xl rounded-xl border border-gray-200 bg-white p-8 shadow-sm">
    @csrf

    @if (session('error'))
        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            {{ session('error') }}
        </div>
    @endif
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
            <label for="student_id" class="block text-sm font-medium text-gray-700">Student</label>
            <select id="student_id" name="student_id" required
                    class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">Choose a student…</option>
                @foreach ($students as $student)
                    <option value="{{ $student->id }}" @selected((string) old('student_id') === (string) $student->id)>
                        {{ $student->name }} ({{ $student->email }})
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="course_id" class="block text-sm font-medium text-gray-700">Course</label>
            <select id="course_id" name="course_id" required
                    class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">Choose a course…</option>
                @foreach ($courses as $course)
                    <option value="{{ $course->id }}" @selected((string) old('course_id') === (string) $course->id)>
                        {{ $course->title }} — {{ $course->instructor->name }}
                    </option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-gray-500">
                Enrolment is not enforced here, so an exception can be granted &mdash; but check the student
                really did the work.
            </p>
        </div>

        <div>
            <label for="final_score" class="block text-sm font-medium text-gray-700">Final score (%)</label>
            <input id="final_score" name="final_score" type="number" step="0.01" min="0" max="100" required
                   value="{{ old('final_score') }}"
                   class="mt-1 block w-32 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            <p class="mt-1 text-xs text-gray-500">
                Written into the integrity hash and printed on the certificate. It cannot be changed afterwards.
            </p>
        </div>

    </div>

    <div class="mt-8 flex items-center gap-3">
        <button type="submit"
                class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
            Issue credential
        </button>
        <a href="{{ route('admin.certificates.index') }}"
           class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
            Cancel
        </a>
    </div>

</form>

@endsection
