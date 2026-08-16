{{-- quizzes/show.blade.php --}}
@extends('layout')

@section('title', $quiz->title)

@section('content')

<a href="{{ route('courses.show', $quiz->course) }}" class="text-sm text-gray-500 hover:text-gray-800">
    &larr; Back to {{ $quiz->course->title }}
</a>

<div class="mt-6 flex flex-wrap items-start justify-between gap-4">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight">{{ $quiz->title }}</h1>
        <p class="mt-1 text-sm text-gray-500">
            {{ $quiz->questions->count() }} questions &middot; {{ $quiz->time_limit }} minute limit
        </p>
    </div>

    @if (! $isOwner && $quiz->questions->isNotEmpty())
        <a href="{{ route('quizzes.attempt', $quiz) }}"
           class="rounded-lg bg-blue-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-blue-800">
            Start quiz
        </a>
    @endif
</div>

@include('partials.flash')

@unless ($isOwner)
    @if ($previousAttempts->isNotEmpty())
        <section class="mt-8 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 bg-gray-50 px-6 py-3">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-600">Your attempts</h2>
            </div>
            <ul class="divide-y divide-gray-100">
                @foreach ($previousAttempts as $attempt)
                    <li class="flex items-center justify-between px-6 py-4">
                        <div>
                            <p class="text-sm text-gray-700">{{ $attempt->created_at->format('j M Y, H:i') }}</p>
                            <p class="text-xs text-gray-500">{{ $attempt->duration_seconds }}s taken</p>
                        </div>
                        <div class="flex items-center gap-4">
                            <span class="inline-flex items-baseline gap-1.5 text-sm font-semibold text-gray-900">
                                {{ $attempt->grade ? rtrim(rtrim(number_format($attempt->grade->calculated_score, 2), '0'), '.').'%' : '—' }}
                                @if ($attempt->grade)
                                    <span class="rounded px-1.5 py-0.5 text-xs font-bold {{ \App\Support\GradeScale::classesFor($attempt->grade->calculated_score) }}">{{ $attempt->grade->letter() }}</span>
                                @endif
                            </span>
                            <a href="{{ route('attempts.show', $attempt) }}"
                               class="text-sm font-medium text-blue-700 hover:text-blue-900">Review</a>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
@endunless

@if ($isOwner)

    <section class="mt-8 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-200 bg-gray-50 px-6 py-3">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-600">Questions</h2>
        </div>
        <ul class="divide-y divide-gray-100">
            @forelse ($quiz->questions as $question)
                <li class="px-6 py-4">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900">{{ $question->question_text }}</p>
                            <p class="mt-1 text-xs text-gray-500">
                                <code>{{ $question->type }}</code> &middot; {{ $strategyNotes[$question->id] }}
                            </p>
                            <ul class="mt-2 space-y-1">
                                @foreach ($question->answers as $answer)
                                    <li class="text-xs {{ $answer->is_correct ? 'font-medium text-emerald-700' : 'text-gray-500' }}">
                                        {{ $answer->is_correct ? '✓' : '·' }} {{ $answer->answer_text }}
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                        <form method="post" action="{{ route('questions.destroy', $question) }}"
                              onsubmit="return confirm('Remove this question?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-xs font-medium text-red-700 hover:text-red-900">Remove</button>
                        </form>
                    </div>
                </li>
            @empty
                <li class="px-6 py-10 text-center text-sm text-gray-500">No questions yet.</li>
            @endforelse
        </ul>
    </section>

    <section class="mt-6 rounded-xl border border-gray-200 bg-white p-8 shadow-sm">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-600">Add a question</h2>

        <form method="post" action="{{ route('quizzes.questions.store', $quiz) }}" class="mt-5 space-y-5">
            @csrf

            <div>
                <label for="type" class="block text-sm font-medium text-gray-700">Type</label>
                <select id="type" name="type" onchange="
                        document.getElementById('mcq-fields').hidden = this.value !== 'mcq';
                        document.getElementById('text-fields').hidden = this.value !== 'text';"
                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    @foreach ($questionTypes as $value => $label)
                        <option value="{{ $value }}" @selected(old('type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-500">
                    The type decides which grading strategy marks it.
                </p>
            </div>

            <div>
                <label for="question_text" class="block text-sm font-medium text-gray-700">Question</label>
                <textarea id="question_text" name="question_text" rows="2" required
                          class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('question_text') }}</textarea>
            </div>

            <div id="mcq-fields" @if (old('type') === 'text') hidden @endif>
                <span class="block text-sm font-medium text-gray-700">Options</span>
                <p class="text-xs text-gray-500">Select the radio button beside the correct one.</p>
                <div class="mt-2 space-y-2">
                    @for ($i = 0; $i < 4; $i++)
                        <div class="flex items-center gap-3">
                            <input type="radio" name="correct_option" value="{{ $i }}" @checked($i === 0)
                                   class="border-gray-300 text-emerald-600 focus:ring-emerald-500">
                            <input type="text" name="options[]" placeholder="Option {{ $i + 1 }}"
                                   class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                    @endfor
                </div>
            </div>

            <div id="text-fields" @if (old('type') !== 'text') hidden @endif>
                <label for="accepted_answers" class="block text-sm font-medium text-gray-700">Accepted answers</label>
                <textarea id="accepted_answers" name="accepted_answers" rows="3"
                          placeholder="One per line. Case and punctuation are ignored, and close typos are accepted."
                          class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('accepted_answers') }}</textarea>
            </div>

            <button type="submit" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
                Add question
            </button>
        </form>
    </section>

@endif

@endsection
