{{--
    The show/hide eye that sits inside a password field.

    Separate from x-password-input because the two plain-HTML password forms
    (the invited registration and the admin re-authentication page) carry this
    project's own field styling rather than Breeze's, and routing them through
    x-text-input would quietly restyle them. They wrap their own input and drop
    this in.

    The parent element must be `relative`, and the input needs `pe-10` so the
    typed characters stay clear of the button.

    Usage: <x-password-toggle for="password" />
--}}

@props(['for'])

<button type="button"
        data-password-toggle="{{ $for }}"
        aria-controls="{{ $for }}"
        aria-pressed="false"
        aria-label="{{ __('Show password') }}"
        title="{{ __('Show password') }}"
        class="absolute inset-y-0 end-0 flex items-center px-3 text-gray-400 transition hover:text-gray-700 focus:outline-none focus-visible:text-gray-700">

    {{-- Shown while the password is masked: press to reveal. --}}
    <svg data-password-icon="show" viewBox="0 0 24 24" fill="none" stroke="currentColor"
         stroke-width="1.6" class="h-5 w-5" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round"
              d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
    </svg>

    {{-- Shown while it is visible: press to hide again. --}}
    <svg data-password-icon="hide" viewBox="0 0 24 24" fill="none" stroke="currentColor"
         stroke-width="1.6" class="hidden h-5 w-5" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round"
              d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
    </svg>
</button>

@once
    <script>
        /*
         * One delegated listener for every password field on the page, bound
         * once however many fields there are -- and it keeps working for
         * markup that arrives later, such as the delete-account modal.
         */
        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-password-toggle]');

            if (! button) {
                return;
            }

            var input = document.getElementById(button.dataset.passwordToggle);

            if (! input) {
                return;
            }

            var reveal = input.type === 'password';

            input.type = reveal ? 'text' : 'password';
            button.setAttribute('aria-pressed', reveal ? 'true' : 'false');
            button.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
            button.setAttribute('title', reveal ? 'Hide password' : 'Show password');

            button.querySelector('[data-password-icon="show"]').classList.toggle('hidden', reveal);
            button.querySelector('[data-password-icon="hide"]').classList.toggle('hidden', ! reveal);

            /*
             * Focus goes back to the field, at the end of what was typed.
             * Without this the caret is lost to the button and typing stops,
             * which is the opposite of helpful when someone is mid-password.
             */
            var end = input.value.length;
            input.focus();
            input.setSelectionRange(end, end);
        });
    </script>
@endonce
