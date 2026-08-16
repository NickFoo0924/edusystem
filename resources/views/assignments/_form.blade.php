{{-- assignments/_form.blade.php -- shared by create and edit --}}

@php
    // Default to "accept" so an instructor who never touches this gets the
    // forgiving policy, exactly as the column default does.
    $current = old('late_policy', isset($assignment) && ! $assignment->allow_late_submission ? 'close' : 'accept');
@endphp

<div class="space-y-5">

    <div>
        <label for="title" class="block text-sm font-medium text-gray-700">Title</label>
        <input id="title" name="title" type="text" required value="{{ old('title', $assignment->title ?? '') }}"
               class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
    </div>

    <div>
        <label for="description" class="block text-sm font-medium text-gray-700">Brief</label>
        <textarea id="description" name="description" rows="4"
                  class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('description', $assignment->description ?? '') }}</textarea>
    </div>

    <div>
        <label for="due_date" class="block text-sm font-medium text-gray-700">Due</label>
        <input id="due_date" name="due_date" type="datetime-local" required
               value="{{ old('due_date', isset($assignment) ? $assignment->due_date->format('Y-m-d\TH:i') : now()->addWeek()->format('Y-m-d\TH:i')) }}"
               class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
    </div>

    {{-- The late submission policy. --}}
    <fieldset>
        <legend class="text-sm font-medium text-gray-700">After the deadline</legend>
        <p class="mt-1 text-xs text-gray-500">What happens when a student tries to hand work in late.</p>

        <div class="mt-3 space-y-3">
            <label class="flex cursor-pointer items-start gap-3 rounded-lg border p-4 {{ $current === 'accept' ? 'border-blue-400 bg-blue-50' : 'border-gray-200' }}">
                <input type="radio" name="late_policy" value="accept" @checked($current === 'accept')
                       class="mt-1 border-gray-300 text-blue-700 focus:ring-blue-500">
                <span>
                    <span class="block text-sm font-medium text-gray-900">
                        Still accept it, but mark it late
                    </span>
                    <span class="mt-0.5 block text-xs text-gray-600">
                        Students can submit at any time. Anything handed in after the deadline is labelled
                        <span class="rounded bg-amber-100 px-1.5 py-0.5 font-medium text-amber-900">Turned in late</span>
                        on your review list, so you can decide what to do about it.
                    </span>
                    <span class="mt-1 block text-[11px] font-medium uppercase tracking-wide text-blue-700">Default</span>
                </span>
            </label>

            <label class="flex cursor-pointer items-start gap-3 rounded-lg border p-4 {{ $current === 'close' ? 'border-red-400 bg-red-50' : 'border-gray-200' }}">
                <input type="radio" name="late_policy" value="close" @checked($current === 'close')
                       class="mt-1 border-gray-300 text-red-700 focus:ring-red-500">
                <span>
                    <span class="block text-sm font-medium text-gray-900">
                        Close it at the deadline
                    </span>
                    <span class="mt-0.5 block text-xs text-gray-600">
                        Uploading and submitting both stop working once the deadline passes. A student who
                        left a draft unsubmitted cannot submit it afterwards.
                    </span>
                </span>
            </label>
        </div>
    </fieldset>

</div>
