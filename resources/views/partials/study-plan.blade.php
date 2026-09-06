{{--
    LearnSync -- Blade view
    Module 2: Academic Resources Repository
    @author Foo Chong Xian
--}}
{{--
    The suggested order to work through a course.

    Only the assessment steps carry a tick. There is no view-tracking table, so
    the system cannot know whether a set of notes has been read, and marking a
    reading step complete on no evidence would put a false claim on a progress
    indicator. The count therefore measures the assessed steps only, and the
    reading steps are shown as guidance.

    Expects: $plan (from App\Support\StudyPlan).
--}}

@php
    $progress = \App\Support\StudyPlan::progress($plan);
    $nextIndex = \App\Support\StudyPlan::nextIndex($plan);
@endphp

<section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 bg-gray-50 px-6 py-3">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-600">Suggested plan</h2>

        @if ($progress['total'] > 0)
            <span class="text-xs text-gray-500">
                {{ $progress['done'] }} of {{ $progress['total'] }} assessed steps done
            </span>
        @endif
    </div>

    @if ($progress['total'] > 0)
        <div class="h-1 w-full bg-gray-100">
            <div class="h-1 bg-emerald-500 transition-all"
                 style="width: {{ $progress['total'] ? round($progress['done'] / $progress['total'] * 100) : 0 }}%"></div>
        </div>
    @endif

    <ol class="divide-y divide-gray-100">
        @foreach ($plan as $i => $step)
            @php
                $isNext = $i === $nextIndex;
            @endphp
            <li class="flex items-start gap-3 px-6 py-3 {{ $isNext ? 'bg-blue-50/60' : '' }}">
                {{-- The marker carries the state: a tick for finished, the step
                     number for anything still to do. --}}
                <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold
                    {{ $step['done']
                        ? 'bg-emerald-100 text-emerald-700'
                        : ($isNext ? 'bg-blue-700 text-white' : 'bg-gray-100 text-gray-500') }}">
                    @if ($step['done'])
                        &check;
                    @else
                        {{ $i + 1 }}
                    @endif
                </span>

                <div class="min-w-0 flex-1">
                    <p class="text-sm {{ $step['done'] ? 'text-gray-500 line-through' : 'font-medium text-gray-900' }}">
                        @if ($step['url'])
                            <a href="{{ $step['url'] }}" class="hover:text-blue-700">{{ $step['title'] }}</a>
                        @else
                            {{ $step['title'] }}
                        @endif
                    </p>
                    <p class="text-xs text-gray-500">
                        <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] uppercase tracking-wide text-gray-600">
                            {{ $step['kind'] }}
                        </span>
                        {{ $step['detail'] }}
                    </p>
                </div>

                @if ($isNext)
                    <span class="shrink-0 self-center rounded-full bg-blue-700 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white">
                        Next
                    </span>
                @endif
            </li>
        @endforeach
    </ol>
</section>
