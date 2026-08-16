{{--
    A circular avatar.

    Shows the uploaded image when there is one, and otherwise the first letter
    of the person's name on a colour derived from that name, so the same person
    always looks the same wherever they appear.

    Usage:
        <x-avatar :user="$user" />              small, for a navbar or list row
        <x-avatar :user="$user" size="lg" />    large, for a profile header
--}}

@props([
    'user',
    'size' => 'sm',
])

@php
    $dimensions = [
        'xs' => 'h-7 w-7 text-xs',
        'sm' => 'h-9 w-9 text-sm',
        'md' => 'h-12 w-12 text-base',
        'lg' => 'h-20 w-20 text-2xl',
    ][$size] ?? 'h-9 w-9 text-sm';

    [$background, $foreground] = $user->avatarColour();
@endphp

@if ($user->avatarUrl())
    <img src="{{ $user->avatarUrl() }}"
         alt="{{ $user->name }}"
         {{ $attributes->merge(['class' => $dimensions.' shrink-0 rounded-full object-cover ring-1 ring-black/5']) }}>
@else
    {{-- aria-hidden because the name is always rendered alongside; the letter
         alone would only add noise for a screen reader. --}}
    <span aria-hidden="true"
          {{ $attributes->merge([
              'class' => $dimensions.' '.$background.' '.$foreground
                  .' flex shrink-0 items-center justify-center rounded-full font-semibold ring-1 ring-black/5',
          ]) }}>
        {{ $user->avatarLetter() }}
    </span>
@endif
