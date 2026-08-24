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

        {{-- Buttons rather than a list of tokens to copy. The author picks a
             field by its name and it is written into the text for them, so the
             placeholder syntax never has to be read or typed. --}}
        <div class="mt-3 rounded-lg bg-gray-50 p-4">
            <p class="text-xs font-medium text-gray-700">
                Insert a detail — it is filled in when the certificate is issued
            </p>

            <div class="mt-2 flex flex-wrap gap-2">
                @foreach (\App\Models\CertificateTemplate::SLOTS as $token => $slot)
                    <button type="button"
                            data-insert-token="{{ $token }}"
                            class="rounded-full border border-gray-300 bg-white px-3 py-1 text-xs font-medium text-gray-700 transition hover:border-blue-400 hover:text-blue-700">
                        {{ $slot['label'] }}
                    </button>
                @endforeach
            </div>

            <p class="mt-2 text-[11px] text-gray-400">
                For example, Student name becomes the holder's full name on the issued certificate.
            </p>
        </div>

        <script>
            // Writes the chosen field into the body at the cursor, so the author
            // never types the placeholder syntax themselves.
            document.addEventListener('click', function (event) {
                var button = event.target.closest('[data-insert-token]');

                if (! button) {
                    return;
                }

                var field = document.getElementById('body_text');
                var token = button.dataset.insertToken;
                var at = field.selectionStart || 0;

                field.value = field.value.slice(0, at) + token + field.value.slice(field.selectionEnd || at);
                field.focus();
                field.setSelectionRange(at + token.length, at + token.length);
            });
        </script>
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
