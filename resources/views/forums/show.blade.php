{{-- forums/show.blade.php --}}
@extends('layout')

@section('title', $forum->title)

@section('content')

<a href="{{ route('courses.show', $forum->course) }}" class="text-sm text-gray-500 hover:text-gray-800">
    &larr; Back to {{ $forum->course->title }}
</a>

<h1 class="mt-6 text-2xl font-semibold tracking-tight">{{ $forum->title }}</h1>
<p class="mt-2 text-sm text-gray-500">
    Ask anything about this course. The instructor is notified when you post.
</p>

@include('partials.flash')

<form method="post" action="{{ route('forums.posts.store', $forum) }}"
      class="mt-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
    @csrf
    <label for="content" class="block text-sm font-medium text-gray-700">Your question</label>
    <textarea id="content" name="content" rows="3" required minlength="3"
              placeholder="What would you like to ask?"
              class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
    <button type="submit"
            class="mt-3 rounded-lg bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
        Post question
    </button>
</form>

<div class="mt-8 space-y-4">

    @forelse ($posts as $post)
        <article id="post-{{ $post->id }}" class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">

            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-900">{{ $post->author->name }}</p>
                    <p class="text-xs text-gray-400">
                        {{ $post->author->role }} &middot; {{ $post->created_at->diffForHumans() }}
                    </p>
                </div>
                @if ($post->user_id === auth()->id() || (auth()->user()->can('forum.moderate') && $forum->course->instructor_id === auth()->id()))
                    <form method="post" action="{{ route('posts.destroy', $post) }}"
                          onsubmit="return confirm('Delete this post and its replies?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-xs font-medium text-red-700 hover:text-red-900">Delete</button>
                    </form>
                @endif
            </div>

            <p class="mt-3 whitespace-pre-line text-sm text-gray-700">{{ $post->content }}</p>

            @if ($post->replies->isNotEmpty())
                <ul class="mt-5 space-y-3 border-l-2 border-gray-100 pl-5">
                    @foreach ($post->replies->sortBy('created_at') as $reply)
                        <li>
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-sm font-medium text-gray-900">
                                        {{ $reply->author->name }}
                                        @if ($reply->user_id === $forum->course->instructor_id)
                                            <span class="ml-1 rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-blue-800">
                                                Instructor
                                            </span>
                                        @endif
                                    </p>
                                    <p class="text-xs text-gray-400">{{ $reply->created_at->diffForHumans() }}</p>
                                </div>
                                @if ($reply->user_id === auth()->id() || (auth()->user()->can('forum.moderate') && $forum->course->instructor_id === auth()->id()))
                                    <form method="post" action="{{ route('replies.destroy', $reply) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs text-red-700 hover:text-red-900">Delete</button>
                                    </form>
                                @endif
                            </div>
                            <p class="mt-1 whitespace-pre-line text-sm text-gray-700">{{ $reply->content }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif

            <form method="post" action="{{ route('posts.replies.store', $post) }}" class="mt-4 flex items-start gap-2">
                @csrf
                <input type="text" name="content" required minlength="2" placeholder="Write a reply…"
                       class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <button type="submit"
                        class="shrink-0 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Reply
                </button>
            </form>

        </article>
    @empty
        <div class="rounded-xl border border-dashed border-gray-300 bg-white p-10 text-center">
            <p class="text-sm text-gray-500">No questions yet. Be the first to ask.</p>
        </div>
    @endforelse

</div>

@endsection
