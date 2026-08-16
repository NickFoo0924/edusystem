{{-- templates/_form.blade.php -- shared by create and edit --}}

<div class="space-y-5">

    <div>
        <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
        <input id="name" name="name" type="text" required value="{{ old('name', $template->name ?? '') }}"
               class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
    </div>

    <div>
        <label for="body_text" class="block text-sm font-medium text-gray-700">Body text</label>
        <textarea id="body_text" name="body_text" rows="8" required
                  class="mt-1 block w-full rounded-lg border-gray-300 font-mono text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('body_text', $template->body_text ?? '') }}</textarea>

        <div class="mt-3 rounded-lg bg-gray-50 p-4">
            <p class="text-xs font-medium text-gray-700">Placeholders substituted at issuance</p>
            <dl class="mt-2 space-y-1">
                @foreach ($placeholders as $token => $meaning)
                    <div class="flex gap-2 text-xs">
                        <dt><code class="rounded bg-white px-1.5 py-0.5 text-blue-700">{{ $token }}</code></dt>
                        <dd class="text-gray-500">{{ $meaning }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <div>
            <label for="signature" class="block text-sm font-medium text-gray-700">Signature image</label>
            <input id="signature" name="signature" type="file" accept="image/*"
                   class="mt-1 block w-full text-sm text-gray-600 file:mr-4 file:rounded-lg file:border-0 file:bg-gray-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-gray-700 hover:file:bg-gray-200">
            @if (! empty($template?->signature_path))
                <img src="{{ Storage::disk('public')->url($template->signature_path) }}" alt=""
                     class="mt-2 h-12 object-contain">
            @endif
        </div>
        <div>
            <label for="background" class="block text-sm font-medium text-gray-700">Background image</label>
            <input id="background" name="background" type="file" accept="image/*"
                   class="mt-1 block w-full text-sm text-gray-600 file:mr-4 file:rounded-lg file:border-0 file:bg-gray-100 file:px-4 file:py-2 file:text-sm file:font-medium file:text-gray-700 hover:file:bg-gray-200">
        </div>
    </div>

    <label class="flex items-center gap-2">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $template->is_active ?? true))
               class="rounded border-gray-300 text-blue-700 focus:ring-blue-500">
        <span class="text-sm text-gray-700">Active</span>
    </label>

</div>
