{{-- quizzes/attempt.blade.php --}}
@extends('layout')

@section('title', $quiz->title)

@section('content')

<div class="mx-auto max-w-2xl">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">{{ $quiz->title }}</h1>
            <p class="mt-1 text-sm text-gray-500">{{ $quiz->questions->count() }} questions</p>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-center">
            <span id="timer" class="font-mono text-lg font-semibold text-gray-900">{{ $quiz->time_limit }}:00</span>
            <span class="block text-[10px] uppercase tracking-wide text-gray-400">remaining</span>
        </div>
    </div>

    @include('partials.flash')

    <form method="post" action="{{ route('quizzes.attempt.store', $quiz) }}" id="quiz-form" class="mt-8 space-y-5">
        @csrf
        <input type="hidden" name="duration_seconds" id="duration_seconds" value="0">

        @foreach ($quiz->questions as $index => $question)
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <p class="text-sm font-medium text-gray-900">
                    <span class="text-gray-400">{{ $index + 1 }}.</span> {{ $question->question_text }}
                </p>

                @if ($question->type === 'mcq')
                    <div class="mt-4 space-y-2">
                        @foreach ($question->answers as $answer)
                            <label class="flex items-center gap-3 rounded-lg border border-gray-200 px-4 py-2.5 hover:bg-gray-50">
                                <input type="radio" name="responses[{{ $question->id }}]" value="{{ $answer->id }}"
                                       class="border-gray-300 text-blue-700 focus:ring-blue-500">
                                <span class="text-sm text-gray-700">{{ $answer->answer_text }}</span>
                            </label>
                        @endforeach
                    </div>
                @else
                    <input type="text" name="responses[{{ $question->id }}]" autocomplete="off"
                           placeholder="Type your answer"
                           class="mt-4 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <p class="mt-1 text-xs text-gray-400">Spelling and capitalisation are treated leniently.</p>
                @endif
            </div>
        @endforeach

        <button type="submit"
                class="w-full rounded-lg bg-blue-700 px-4 py-3 text-sm font-medium text-white hover:bg-blue-800">
            Submit answers
        </button>
    </form>

</div>

<script>
    // Counts the time taken and hands the paper in automatically when the
    // limit runs out, so an unattended tab cannot sit open indefinitely.
    (function () {
        var limit = {{ $quiz->time_limit }} * 60;
        var elapsed = 0;
        var display = document.getElementById('timer');
        var field = document.getElementById('duration_seconds');
        var form = document.getElementById('quiz-form');

        var tick = setInterval(function () {
            elapsed += 1;
            field.value = elapsed;

            var left = Math.max(0, limit - elapsed);
            var minutes = Math.floor(left / 60);
            var seconds = left % 60;
            display.textContent = minutes + ':' + (seconds < 10 ? '0' : '') + seconds;

            if (left <= 30) {
                display.classList.add('text-red-600');
            }

            if (left === 0) {
                clearInterval(tick);
                form.submit();
            }
        }, 1000);
    })();
</script>

@endsection
