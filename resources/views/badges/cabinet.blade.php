{{--
    LearnSync -- Blade view
    Module 1: Identity, Access & Digital Credentialing
    @author Serena Lim Sze Kee
--}}
{{-- badges/cabinet.blade.php --}}
@extends('layout')

@section('title', 'Trophy Cabinet')

@section('content')

<h1 class="text-2xl font-semibold tracking-tight">Trophy Cabinet</h1>
<p class="mt-2 text-sm text-gray-500">
    {{ $earned->count() }} of {{ $badges->count() }} badges earned. Locked badges show what unlocks them.
</p>

<div class="mt-8 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">

    @foreach ($badges as $badge)
        @php
            $isEarned = $earned->has($badge->id);
            $medal = [
                'bronze' => ['ring' => 'border-amber-300', 'fill' => '#b45309', 'text' => 'text-amber-800', 'chip' => 'bg-amber-100 text-amber-800'],
                'silver' => ['ring' => 'border-slate-300', 'fill' => '#64748b', 'text' => 'text-slate-700', 'chip' => 'bg-slate-100 text-slate-700'],
                'gold'   => ['ring' => 'border-yellow-400', 'fill' => '#ca8a04', 'text' => 'text-yellow-800', 'chip' => 'bg-yellow-100 text-yellow-800'],
            ][$badge->tier];
        @endphp

        <div class="rounded-xl border bg-white p-6 shadow-sm {{ $isEarned ? $medal['ring'] : 'border-gray-200' }}">

            <div class="flex items-start gap-4">

                <div class="{{ $isEarned ? '' : 'opacity-30 grayscale' }}">
                    @if ($badge->iconUrl())
                        <img src="{{ $badge->iconUrl() }}" alt="" class="h-14 w-14 rounded-full object-cover">
                    @else
                        {{-- Built-in medal, used when no custom icon has been uploaded. --}}
                        <svg viewBox="0 0 48 48" class="h-14 w-14" aria-hidden="true">
                            <circle cx="24" cy="19" r="13" fill="{{ $medal['fill'] }}" />
                            <circle cx="24" cy="19" r="9" fill="none" stroke="#ffffff" stroke-width="1.5" opacity="0.7" />
                            <path d="M16 30 L12 44 L24 38 L36 44 L32 30 Z" fill="{{ $medal['fill'] }}" opacity="0.85" />
                        </svg>
                    @endif
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <h2 class="truncate font-semibold {{ $isEarned ? 'text-gray-900' : 'text-gray-400' }}">
                            {{ $badge->name }}
                        </h2>
                        <span class="rounded-full px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide {{ $isEarned ? $medal['chip'] : 'bg-gray-100 text-gray-400' }}">
                            {{ $badge->tier }}
                        </span>
                    </div>

                    <p class="mt-1 text-sm {{ $isEarned ? 'text-gray-600' : 'text-gray-400' }}">
                        {{ $badge->description }}
                    </p>

                    @if ($isEarned)
                        <p class="mt-3 text-xs font-medium {{ $medal['text'] }}">
                            Earned {{ \Illuminate\Support\Carbon::parse($earned[$badge->id]->pivot->awarded_at)->format('j M Y') }}
                        </p>
                    @else
                        <p class="mt-3 inline-flex items-center gap-1 rounded-md bg-gray-50 px-2 py-1 text-xs text-gray-500">
                            <svg viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3">
                                <path fill-rule="evenodd" d="M10 1a4 4 0 00-4 4v2H5a2 2 0 00-2 2v7a2 2 0 002 2h10a2 2 0 002-2V9a2 2 0 00-2-2h-1V5a4 4 0 00-4-4zm2 6V5a2 2 0 10-4 0v2h4z" clip-rule="evenodd" />
                            </svg>
                            Locked
                        </p>
                    @endif
                </div>

            </div>
        </div>
    @endforeach

</div>

@if ($badges->isEmpty())
    <div class="mt-8 rounded-xl border border-dashed border-gray-300 bg-white p-10 text-center">
        <p class="text-sm text-gray-500">No badge rules are active yet.</p>
    </div>
@endif

@endsection
