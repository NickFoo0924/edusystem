{{-- badges/_form.blade.php -- shared by create and edit --}}

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
        <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
        <input id="name" name="name" type="text" required
               value="{{ old('name', $badge->name ?? '') }}"
               class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
    </div>

    <div>
        <label for="description" class="block text-sm font-medium text-gray-700">
            Unlock condition
        </label>
        <input id="description" name="description" type="text" required
               value="{{ old('description', $badge->description ?? '') }}"
               placeholder="Score 90% or higher on any quiz."
               class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
        <p class="mt-1 text-xs text-gray-500">
            Shown under the greyed-out badge in a student's trophy cabinet, so write it as an instruction.
        </p>
    </div>

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <div>
            <label for="tier" class="block text-sm font-medium text-gray-700">Tier</label>
            <select id="tier" name="tier"
                    class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                @foreach ($tiers as $tier)
                    <option value="{{ $tier }}" @selected(old('tier', $badge->tier ?? '') === $tier)>
                        {{ ucfirst($tier) }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="criteria_value" class="block text-sm font-medium text-gray-700">Threshold value</label>
            <input id="criteria_value" name="criteria_value" type="number" min="1" required
                   value="{{ old('criteria_value', $badge->criteria_value ?? 1) }}"
                   class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
        </div>
    </div>

    <div>
        <label for="criteria_type" class="block text-sm font-medium text-gray-700">Criteria</label>
        <select id="criteria_type" name="criteria_type"
                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            @foreach ($criteriaTypes as $value => $label)
                <option value="{{ $value }}" @selected(old('criteria_type', $badge->criteria_type ?? '') === $value)>
                    {{ $label }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <label for="icon" class="block text-sm font-medium text-gray-700">Icon (optional)</label>
        <input id="icon" name="icon" type="file" accept="image/*"
               class="mt-1 block w-full text-sm text-gray-600 file:mr-4 file:rounded-lg file:border-0 file:bg-gray-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-gray-700 hover:file:bg-gray-200">
        <p class="mt-1 text-xs text-gray-500">
            Cropped to a 128&times;128 PNG on upload. Leave empty to use the built-in medal for the tier.
        </p>
        @if (! empty($badge?->icon_path) && $badge->iconUrl())
            <img src="{{ $badge->iconUrl() }}" alt="" class="mt-3 h-14 w-14 rounded-full object-cover">
        @endif
    </div>

    <label class="flex items-center gap-2">
        <input type="checkbox" name="is_active" value="1"
               @checked(old('is_active', $badge->is_active ?? true))
               class="rounded border-gray-300 text-blue-700 focus:ring-blue-500">
        <span class="text-sm text-gray-700">Active &mdash; the rules engine evaluates this badge</span>
    </label>

</div>
