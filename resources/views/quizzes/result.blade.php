{{-- quizzes/result.blade.php --}}
@extends('layout')

@section('title', 'Quiz result')

@section('content')

<div class="mx-auto max-w-2xl">

    <a href="{{ route('quizzes.show', $attempt->quiz) }}" class="text-sm text-gray-500 hover:text-gray-800">
        &larr; Back to {{ $attempt->quiz->title }}
    </a>

    <div class="mt-6 rounded-xl border border-gray-200 bg-white p-8 text-center shadow-sm">
        <p class="text-xs uppercase tracking-wide text-gray-500">
            {{ $isOwner ? 'Your score' : $attempt->student->name }}
        </p>
        <p class="mt-2 text-4xl font-semibold text-gray-900">
            {{ $attempt->grade ? rtrim(rtrim(number_format($attempt->grade->calculated_score, 2), '0'), '.') : '—' }}%
        </p>
        @if ($attempt->grade)
            <p class="mt-2">
                <span class="rounded-lg px-3 py-1 text-lg font-bold {{ \App\Support\GradeScale::classesFor($attempt->grade->calculated_score) }}">
                    {{ $attempt->grade->letter() }}
                </span>
            </p>
        @endif
        <p class="mt-2 text-sm text-gray-500">
            {{ $attempt->answers->where('is_correct', true)->count() }} of {{ $attempt->answers->count() }} correct
            &middot; {{ $attempt->duration_seconds }}s taken
        </p>
    </div>

    <div class="mt-6 space-y-4">
        @foreach ($attempt->answers as $answer)
            @php $question = $answer->question; @endphp
            <div class="rounded-xl border bg-white p-6 shadow-sm {{ $answer->is_correct ? 'border-emerald-200' : 'border-red-200' }}">

                <div class="flex items-start justify-between gap-4">
                    <p class="text-sm font-medium text-gray-900">{{ $question->question_text }}</p>
                    <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide
                        {{ $answer->is_correct ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800' }}">
                        {{ $answer->is_correct ? 'Correct' : 'Incorrect' }}
                    </span>
                </div>

                <dl class="mt-3 space-y-1 text-sm">
                    <div class="flex gap-2">
                        <dt class="text-gray-500">Your answer:</dt>
                        <dd class="text-gray-900">
                            @if ($question->type === 'multi')
                                @php
                                    // The response holds a comma-separated list of answer ids.
                                    $chosenIds = collect(explode(',', (string) $answer->response))
                                        ->map(fn ($id) => (int) trim($id))->filter();
                                    $chosen = $question->answers->whereIn('id', $chosenIds);
                                @endphp
                                @forelse ($chosen as $option)
                                    <span class="mr-1 inline-block rounded px-1.5 py-0.5 text-xs
                                        {{ $option->is_correct ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800' }}">
                                        {{ $option->answer_text }}
                                    </span>
                                @empty
                                    —
                                @endforelse
                            @elseif ($question->type === 'mcq')
                                {{ $question->answers->firstWhere('id', (int) $answer->response)?->answer_text ?? '—' }}
                            @else
                                {{ $answer->response ?: '—' }}
                            @endif
                        </dd>
                    </div>
                    @unless ($answer->is_correct)
                        <div class="flex gap-2">
                            <dt class="text-gray-500">Expected:</dt>
                            <dd class="text-emerald-800">
                                {{ $question->answers->where('is_correct', true)->pluck('answer_text')->implode(' / ') }}
                            </dd>
                        </div>
                    @endunless
                </dl>

                <p class="mt-2 text-xs text-gray-400">
                    Marked {{ rtrim(rtrim(number_format($answer->awarded_score, 2), '0'), '.') }} / 1
                </p>
            </div>
        @endforeach
    </div>

</div>

@endsection
