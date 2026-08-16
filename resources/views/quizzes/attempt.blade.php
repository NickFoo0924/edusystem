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

                {{-- Always say how many answers are wanted, so nobody has to
                     guess from the shape of the controls. --}}
                <p class="mt-1 text-xs font-medium {{ $question->type === 'multi' ? 'text-blue-700' : 'text-gray-400' }}">
                    {{ $question->selectionInstruction() }}
                </p>

                @if ($question->type === 'multi')
                    @php $required = $question->requiredSelections(); @endphp
                    <div class="mt-4 space-y-2"
                         data-multi-group
                         data-required="{{ $required }}"
                         data-question="{{ $index + 1 }}">
                        @foreach ($question->answers as $answer)
                            <label class="flex items-center gap-3 rounded-lg border border-gray-200 px-4 py-2.5 hover:bg-gray-50">
                                <input type="checkbox" name="responses[{{ $question->id }}][]" value="{{ $answer->id }}"
                                       class="rounded border-gray-300 text-blue-700 focus:ring-blue-500">
                                <span class="text-sm text-gray-700">{{ $answer->answer_text }}</span>
                            </label>
                        @endforeach
                    </div>
                    {{-- Live count, so the requirement is visible while choosing
                         rather than only on submit. --}}
                    <p class="mt-2 text-xs" data-multi-counter>
                        <span class="font-medium">0</span> of {{ $required }} selected
                    </p>
                @elseif ($question->type === 'mcq')
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

        <p id="submit-warning" class="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900" hidden></p>

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
                // Time is up: hand in whatever is there, incomplete or not.
                form.submit();
            }
        }, 1000);
    })();

    /*
     * Multiple-answer questions must have exactly the required number ticked.
     *
     * Two behaviours: a live counter while choosing, and a block on submit
     * naming the questions that are wrong. Extra ticks past the limit are
     * refused outright, so a student cannot overshoot and then wonder which to
     * remove.
     */
    (function () {
        var groups = Array.prototype.slice.call(document.querySelectorAll('[data-multi-group]'));

        if (!groups.length) {
            return;
        }

        var form = document.getElementById('quiz-form');
        var warning = document.getElementById('submit-warning');

        function selected(group) {
            return group.querySelectorAll('input[type=checkbox]:checked').length;
        }

        function refresh(group) {
            var required = parseInt(group.dataset.required, 10);
            var count = selected(group);
            var counter = group.parentElement.querySelector('[data-multi-counter]');

            counter.querySelector('span').textContent = count;
            counter.classList.toggle('text-emerald-700', count === required);
            counter.classList.toggle('text-gray-500', count !== required);

            // Once the limit is reached, the unticked boxes stop accepting
            // clicks rather than silently allowing an invalid answer.
            group.querySelectorAll('input[type=checkbox]').forEach(function (box) {
                box.disabled = !box.checked && count >= required;
                box.parentElement.classList.toggle('opacity-50', box.disabled);
            });
        }

        groups.forEach(function (group) {
            group.addEventListener('change', function () { refresh(group); });
            refresh(group);
        });

        form.addEventListener('submit', function (event) {
            var incomplete = groups.filter(function (group) {
                return selected(group) !== parseInt(group.dataset.required, 10);
            });

            if (!incomplete.length) {
                return;
            }

            event.preventDefault();

            var detail = incomplete.map(function (group) {
                return 'question ' + group.dataset.question + ' needs ' + group.dataset.required;
            }).join(', ');

            warning.textContent = 'Select the required number of answers before submitting — ' + detail + '.';
            warning.hidden = false;
            incomplete[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    })();
</script>

@endsection
