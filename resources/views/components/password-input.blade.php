{{--
    A Breeze-styled password field with a show/hide control.

    Typing a password you cannot see is the main reason people get locked out,
    and this system locks an account after five wrong attempts -- which only an
    administrator can clear. Being able to check what you typed is worth more
    here than it would be elsewhere.

    The field starts masked and every navigation returns it to masked, because
    the toggle is deliberately not remembered: revealing is a decision for the
    moment, not a setting.

    Usage: <x-password-input id="password" name="password" required
                             autocomplete="current-password" class="block mt-1 w-full" />
--}}

@props(['id'])

<div class="relative">
    {{-- pe-10 keeps the typed characters clear of the button. --}}
    <x-text-input :id="$id" type="password" {{ $attributes->merge(['class' => 'pe-10']) }} />

    <x-password-toggle :for="$id" />
</div>
