{{-- announcements/index.blade.php --}}
@extends('layout')

@section('title', 'Announcements')

@section('content')

<div class="flex items-center justify-between">
    <h1 class="text-2xl font-semibold tracking-tight">Announcements</h1>
    @can('announcement.create')
        <a href="{{ route('announcements.create') }}"
           class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
            Post announcement
        </a>
    @endcan
</div>

@include('partials.flash')

<div class="mt-8 space-y-4">
    @forelse ($announcements as $announcement)
        <div id="announcement-{{ $announcement->id }}"
             class="scroll-mt-4 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="flex items-center gap-2">
                    @if ($announcement->isGlobal())
                        <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-indigo-800">
                            Global
                        </span>
                    @else
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-gray-600">
                            {{ $announcement->course->title }}
                        </span>
                    @endif
                </div>

                @if ($announcement->author_id === auth()->id() || auth()->user()->can('analytics.view_system'))
                    <form method="post" action="{{ route('announcements.destroy', $announcement) }}"
                          onsubmit="return confirm('Delete this announcement?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-xs font-medium text-red-700 hover:text-red-900">Delete</button>
                    </form>
                @endif
            </div>

            <p class="mt-3 whitespace-pre-line text-sm text-gray-700">{{ $announcement->content }}</p>
            <p class="mt-3 text-xs text-gray-400">
                {{ $announcement->author->name }} &middot; {{ $announcement->created_at->diffForHumans() }}
            </p>

            @include('partials.announcement-comments')
        </div>
    @empty
        <div class="rounded-xl border border-dashed border-gray-300 bg-white p-10 text-center">
            <p class="text-sm text-gray-500">No announcements.</p>
        </div>
    @endforelse
</div>

@endsection
