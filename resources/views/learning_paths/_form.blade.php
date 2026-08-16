{{-- learning_paths/_form.blade.php -- shared by create and edit --}}

@if ($errors->any())
    <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        <ul class="list-inside list-disc space-y-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@php
    // Course id => sequence, for the courses already in this path.
    $selected = isset($path)
        ? $path->courses->pluck('pivot.sequence', 'id')->all()
        : [];
@endphp

<div class="space-y-5">

    <div>
        <label for="title" class="block text-sm font-medium text-gray-700">Title</label>
        <input id="title" name="title" type="text" required
               value="{{ old('title', $path->title ?? '') }}"
               placeholder="Web Development Pathway"
               class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
    </div>

    <div>
        <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
        <textarea id="description" name="description" rows="3" required
                  class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('description', $path->description ?? '') }}</textarea>
    </div>

    <div>
        <label for="certificate_template_id" class="block text-sm font-medium text-gray-700">
            Pathway certificate template
        </label>
        <select id="certificate_template_id" name="certificate_template_id"
                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            <option value="">Use the standard active template</option>
            @foreach ($templates as $template)
                <option value="{{ $template->id }}"
                    @selected((string) old('certificate_template_id', $path->certificate_template_id ?? '') === (string) $template->id)>
                    {{ $template->name }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <span class="block text-sm font-medium text-gray-700">Courses in this path</span>
        <p class="mt-1 text-xs text-gray-500">
            Tick each course and give it a step number. The path is only awarded once a student holds a
            valid certificate for every one of them.
        </p>

        <div class="mt-3 divide-y divide-gray-100 overflow-hidden rounded-lg border border-gray-200">
            @forelse ($courses as $course)
                @php $isIn = array_key_exists($course->id, $selected); @endphp
                <label class="flex items-center gap-4 bg-white px-4 py-3 hover:bg-gray-50">
                    <input type="checkbox" name="course_ids[]" value="{{ $course->id }}"
                           @checked(in_array($course->id, old('course_ids', array_keys($selected))))
                           class="rounded border-gray-300 text-blue-700 focus:ring-blue-500">

                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-medium text-gray-900">{{ $course->title }}</span>
                        <span class="block text-xs text-gray-500">{{ $course->instructor->name }}</span>
                    </span>

                    <span class="flex items-center gap-2">
                        <span class="text-xs text-gray-500">Step</span>
                        <input type="number" min="1" name="sequence[{{ $course->id }}]"
                               value="{{ old('sequence.'.$course->id, $selected[$course->id] ?? '') }}"
                               class="w-20 rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </span>
                </label>
            @empty
                <p class="bg-white px-4 py-6 text-center text-sm text-gray-500">
                    No courses exist yet. Module 2 owns course creation.
                </p>
            @endforelse
        </div>
    </div>

    <label class="flex items-center gap-2">
        <input type="checkbox" name="is_active" value="1"
               @checked(old('is_active', $path->is_active ?? true))
               class="rounded border-gray-300 text-blue-700 focus:ring-blue-500">
        <span class="text-sm text-gray-700">Active &mdash; pathway certificates may be issued from this path</span>
    </label>

</div>
