{{--
    LearnSync -- Blade view
    Module 3: Student Forum & Notifications
    @author Ong Shun Yan
--}}
{{-- forums/show.blade.php --}}
@extends('layout')

@section('title', $forum->title)

@php
    $course = $forum->course;
    $me = auth()->user();
    $canModerate = $me->can('forum.moderate') && $course->instructor_id === $me->id;
    // Offered as datalist options so a name can be picked rather than spelled.
    $handles = \App\Support\Mentions::candidates($course);
@endphp

@section('content')

<a href="{{ route('courses.show', $course) }}" class="text-sm text-gray-500 hover:text-gray-800">
    &larr; Back to {{ $course->title }}
</a>

<h1 class="mt-6 text-2xl font-semibold tracking-tight">{{ $forum->title }}</h1>
<p class="mt-2 text-sm text-gray-500">
    Ask anything about this course. Type <span class="font-mono text-gray-700">@</span> and a name to
    tag someone &mdash; they are notified. The instructor is notified of every new question.
</p>

@include('partials.flash')

{{-- Ask -------------------------------------------------------------------}}
<form method="post" action="{{ route('forums.posts.store', $forum) }}"
      class="mt-6 flex items-start gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
    @csrf
    <x-avatar :user="$me" size="sm" />

    <div class="min-w-0 flex-1">
        <label for="content" class="sr-only">Your question</label>
        <textarea id="content" name="content" rows="2" required minlength="3"
                  placeholder="Ask a question… use @name to tag someone"
                  class="block w-full resize-y rounded-xl border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>

        <div class="mt-2 flex items-center justify-between gap-3">
            <p class="truncate text-xs text-gray-400">
                In this course: {{ $course->students()->count() }} students and {{ $course->instructor->name }}
            </p>
            <button type="submit"
                    class="shrink-0 rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
                Post
            </button>
        </div>
    </div>
</form>

{{-- Thread ----------------------------------------------------------------}}
<div class="mt-8 space-y-6">

    @forelse ($posts as $post)
        @php $askedByInstructor = $post->user_id === $course->instructor_id; @endphp

        <article id="post-{{ $post->id }}" class="scroll-mt-4">

            {{-- The question --}}
            <div class="flex items-start gap-3">
                <x-avatar :user="$post->author" size="sm" />

                <div class="min-w-0 flex-1">
                    <div class="rounded-2xl rounded-tl-sm border px-4 py-3
                        {{ $askedByInstructor ? 'border-blue-200 bg-blue-50' : 'border-gray-200 bg-white' }}">
                        <div class="flex flex-wrap items-baseline gap-x-2">
                            <span class="text-sm font-medium text-gray-900">{{ $post->author->name }}</span>
                            @if ($askedByInstructor)
                                <span class="rounded-full bg-blue-100 px-2 text-[10px] font-medium uppercase tracking-wide text-blue-800">
                                    Instructor
                                </span>
                            @endif
                            <span class="text-[11px] text-gray-400">{{ $post->created_at->diffForHumans() }}</span>

                            @if ($post->user_id === $me->id || $canModerate)
                                <form method="post" action="{{ route('posts.destroy', $post) }}"
                                      onsubmit="return confirm('Delete this post and its replies?');"
                                      class="ms-auto">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-[11px] text-gray-400 hover:text-red-700">Delete</button>
                                </form>
                            @endif
                        </div>

                        {{-- Escaped inside highlight(), then the @names are
                             wrapped -- so a message containing markup is still
                             shown as text. --}}
                        <p class="mt-1 whitespace-pre-line break-words text-sm text-gray-700">
                            {!! \App\Support\Mentions::highlight($post->content, $course) !!}
                        </p>
                    </div>

                    {{-- Replies, indented under the question --}}
                    @if ($post->replies->isNotEmpty())
                        <ul class="mt-3 space-y-3 ps-6">
                            @foreach ($post->replies->sortBy('created_at') as $reply)
                                @php $fromInstructor = $reply->user_id === $course->instructor_id; @endphp
                                <li class="flex items-start gap-2.5">
                                    <x-avatar :user="$reply->author" size="sm" />

                                    <div class="min-w-0 flex-1 rounded-2xl rounded-tl-sm border px-3 py-2
                                        {{ $fromInstructor ? 'border-blue-200 bg-blue-50' : 'border-gray-200 bg-gray-50' }}">
                                        <div class="flex flex-wrap items-baseline gap-x-2">
                                            <span class="text-xs font-medium text-gray-900">{{ $reply->author->name }}</span>
                                            @if ($fromInstructor)
                                                <span class="rounded-full bg-blue-100 px-1.5 text-[10px] font-medium uppercase tracking-wide text-blue-800">
                                                    Instructor
                                                </span>
                                            @endif
                                            <span class="text-[11px] text-gray-400">{{ $reply->created_at->diffForHumans() }}</span>

                                            @if ($reply->user_id === $me->id || $canModerate)
                                                <form method="post" action="{{ route('replies.destroy', $reply) }}" class="ms-auto">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-[11px] text-gray-400 hover:text-red-700">Delete</button>
                                                </form>
                                            @endif
                                        </div>

                                        <p class="mt-0.5 whitespace-pre-line break-words text-sm text-gray-700">
                                            {!! \App\Support\Mentions::highlight($reply->content, $course) !!}
                                        </p>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    {{-- Reply box --}}
                    <form method="post" action="{{ route('posts.replies.store', $post) }}"
                          class="mt-3 flex items-center gap-2 ps-6">
                        @csrf
                        <input type="text" name="content" required minlength="2"
                               list="forum-handles"
                               placeholder="Reply… use @name to tag someone"
                               class="block w-full rounded-full border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <button type="submit"
                                class="shrink-0 rounded-full bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">
                            Send
                        </button>
                    </form>
                </div>
            </div>

        </article>
    @empty
        <div class="rounded-xl border border-dashed border-gray-300 bg-white p-10 text-center">
            <p class="text-sm text-gray-500">No questions yet. Be the first to ask.</p>
        </div>
    @endforelse

</div>

{{-- Every handle the parser will accept in this course, so the browser can
     suggest them instead of the reader having to guess the spelling. --}}
<datalist id="forum-handles">
    @foreach ($handles as $handle => $person)
        {{-- '@'.$handle rather than @{{ ... }}, which Blade reads as an escape
             for a literal brace pair and would print the expression itself. --}}
        <option value="{{ '@'.$handle }}">{{ $person->name }}</option>
    @endforeach
</datalist>

@endsection
