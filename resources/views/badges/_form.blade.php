{{--
    LearnSync -- Blade view
    Module 1: Identity, Access & Digital Credentialing
    @author Serena Lim Sze Kee
--}}
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

    {{-- What satisfying this rule produces. Badge rules and certificate rules
         live in the same registry and run through the same condition
         evaluator; this is the only thing that differs between them. --}}
    <div>
        <label for="award_type" class="block text-sm font-medium text-gray-700">Award</label>
        <select id="award_type" name="award_type"
                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            @foreach ($awardTypes as $value => $label)
                <option value="{{ $value }}"
                        @selected(old('award_type', $badge->award_type ?? 'badge') === $value)>
                    {{ $label }}
                </option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-gray-500">
            A certificate rule must name a subject below, and mints a real credential &mdash; unique ID,
            integrity hash, QR-coded PDF &mdash; through the same authority as an automatic issuance.
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

    {{--
        Only meaningful for the one subject-scoped criterion. Left visible for
        every type rather than hidden by JavaScript, because the controller
        clears it server-side anyway -- so a stale value cannot survive a switch
        of criteria, with or without scripting.
    --}}
    <div>
        <label for="course_id" class="block text-sm font-medium text-gray-700">
            Subject <span class="font-normal text-gray-500">(only used by "every quiz in a subject")</span>
        </label>
        <select id="course_id" name="course_id"
                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            <option value="">Any subject — use the threshold value as "how many subjects"</option>
            @foreach ($courses as $course)
                <option value="{{ $course->id }}"
                        @selected((int) old('course_id', $badge->course_id ?? 0) === $course->id)>
                    {{ $course->code }} {{ $course->title }}
                </option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-gray-500">
            Pick a subject for a badge like "Subject Expert &mdash; Integrative Programming". One badge
            per subject, so a student can hold several.
        </p>
    </div>

    {{-- Certificate rules only. A badge rule renders its tier medal instead,
         and the controller clears this column for them. --}}
    <div>
        <label for="certificate_template_id" class="block text-sm font-medium text-gray-700">
            Certificate design <span class="font-normal text-gray-500">(certificate rules only)</span>
        </label>
        <select id="certificate_template_id" name="certificate_template_id"
                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            <option value="">Use the active default template</option>
            @foreach ($templates as $template)
                <option value="{{ $template->id }}"
                        @selected((int) old('certificate_template_id', $badge->certificate_template_id ?? 0) === $template->id)>
                    {{ $template->name }}
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
