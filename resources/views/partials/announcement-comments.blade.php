{{--
    The conversation under one announcement.

    Collapsed behind a <details> rather than a JavaScript toggle: a notice with
    forty comments should not push the next announcement off the screen, and
    <details> gets the open/closed behaviour, the keyboard support and the
    "find on page auto-expands it" behaviour from the browser for free.

    Included from both the announcements page and the course page, so the
    thread reads the same wherever it is met.

    Expects: $announcement (with `comments.author` eager-loaded by the caller).
--}}

@php
    $comments = $announcement->comments;
    $canComment = auth()->user()->can('announcement.comment')
        && $announcement->isVisibleTo(auth()->user());
@endphp

<details class="mt-4 border-t border-gray-100 pt-3" @if ($errors->any() && old('announcement_id') == $announcement->id) open @endif>
    <summary class="cursor-pointer list-none text-xs font-medium text-blue-700 hover:text-blue-900">
        <span class="inline-flex items-center gap-1.5">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" class="h-4 w-4">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />
            </svg>
            @if ($comments->isEmpty())
                {{ $canComment ? 'Add a comment' : 'No comments' }}
            @else
                View {{ $comments->count() }} {{ Str::plural('comment', $comments->count()) }}
            @endif
        </span>
    </summary>

    @if ($comments->isNotEmpty())
        <ul class="mt-3 space-y-3">
            @foreach ($comments as $comment)
                @php
                    // The lecturer's own replies are marked, so a student can
                    // tell an answer from a classmate's guess at a glance.
                    $fromAuthor = $comment->user_id === $announcement->author_id;
                @endphp
                <li class="flex items-start gap-2.5">
                    <x-avatar :user="$comment->author" size="sm" />

                    <div class="min-w-0 flex-1 rounded-xl px-3 py-2 {{ $fromAuthor ? 'bg-blue-50' : 'bg-gray-50' }}">
                        <div class="flex flex-wrap items-baseline gap-x-2">
                            <span class="text-xs font-medium text-gray-900">{{ $comment->author->name }}</span>
                            @if ($fromAuthor)
                                <span class="rounded-full bg-blue-100 px-1.5 text-[10px] font-medium text-blue-800">
                                    author
                                </span>
                            @endif
                            <span class="text-[11px] text-gray-400">{{ $comment->created_at->diffForHumans() }}</span>

                            @if ($comment->user_id === auth()->id()
                                || $announcement->author_id === auth()->id()
                                || auth()->user()->can('analytics.view_system'))
                                <form method="post"
                                      action="{{ route('announcements.comments.destroy', [$announcement, $comment]) }}"
                                      onsubmit="return confirm('Delete this comment?');"
                                      class="ml-auto">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-[11px] text-gray-400 hover:text-red-700">
                                        Delete
                                    </button>
                                </form>
                            @endif
                        </div>

                        <p class="mt-0.5 whitespace-pre-line break-words text-sm text-gray-700">{{ $comment->body }}</p>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($canComment)
        <form method="post" action="{{ route('announcements.comments.store', $announcement) }}"
              class="mt-3 flex items-start gap-2.5">
            @csrf
            {{-- Tells the error handler which thread to reopen on a failed
                 submit; without it the message would appear under a collapsed
                 summary the user never sees. --}}
            <input type="hidden" name="announcement_id" value="{{ $announcement->id }}">

            <x-avatar :user="auth()->user()" size="sm" />

            <div class="min-w-0 flex-1">
                <textarea name="body" rows="2" required maxlength="1000"
                          placeholder="Add a comment…"
                          class="block w-full resize-y rounded-xl border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('announcement_id') == $announcement->id ? old('body') : '' }}</textarea>

                @if ($errors->any() && old('announcement_id') == $announcement->id)
                    <x-input-error :messages="$errors->get('body')" class="mt-1" />
                @endif

                <button type="submit"
                        class="mt-2 rounded-lg bg-blue-700 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-800">
                    Comment
                </button>
            </div>
        </form>
    @endif
</details>
