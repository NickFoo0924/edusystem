<!-- layout.blade.php -->
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>

    {{-- The tab icon. favicon.png is a 64px cut of the same artwork, small
         enough that it costs nothing on every page; logo.png is the 256px
         one iOS uses when the site is saved to a home screen. --}}
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('logo.png') }}">

    {{-- Restore the rail's open/closed state before the first paint, so it
         never flashes open and then snap shut. --}}
    <script>
        (function () {
            var stored = localStorage.getItem('learnsync.sidebar');
            var narrow = window.matchMedia('(max-width: 767px)').matches;

            if (stored === 'closed' || (stored === null && narrow)) {
                document.documentElement.classList.add('sidebar-closed');
            }
        })();
    </script>

    <style>
        /* Width and label visibility are plain CSS rather than utility classes,
           because they are driven by a class on <html> that Tailwind would need
           an arbitrary variant to reach. */
        #sidebar {
            width: 15.5rem;
            transition: width 150ms ease;
        }

        .sidebar-closed #sidebar { width: 4.5rem; }

        .sidebar-closed .sidebar-label,
        .sidebar-closed .sidebar-group-title { display: none; }

        /* Collapsed, the group headings go, so a rule stands in for them. */
        .sidebar-closed .sidebar-divider { display: block; }

        /* Dashboard lists show three rows until asked for the rest. Plain CSS
           rather than a utility class, because the rows must already be hidden
           on first paint -- hiding them from script would show the full list
           and then collapse it. */
        .expandable-list:not(.is-open) > *:nth-child(n+4) { display: none; }

        .sidebar-closed .sidebar-link {
            justify-content: center;
            padding-left: 0.75rem;
            padding-right: 0.75rem;
        }
    </style>

    {{-- Page data for scripts must be defined before the bundle runs. --}}
    @stack('scripts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-50 font-sans text-gray-900 antialiased">

@auth

    {{-- The signed-in shell is exactly the viewport tall and never scrolls
         itself. The rail and the content pane each scroll independently
         inside it, so moving one leaves the other where it was. --}}
    <div class="flex h-screen flex-col overflow-hidden">

    {{-- TOP BAR: menu toggle, brand, and the account controls only.
         Everything else lives in the rail. --}}
    <header class="flex shrink-0 items-center justify-between gap-3 border-b border-gray-200 bg-white px-4 py-2.5">
        <div class="flex items-center gap-3">
            <button type="button" id="sidebar-toggle"
                    title="Main menu" aria-label="Toggle the main menu" aria-expanded="true"
                    class="rounded-full p-2 text-gray-600 transition hover:bg-gray-100">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-6 w-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                </svg>
            </button>

            <a href="{{ route('dashboard') }}"
               class="flex items-center gap-2 text-lg font-semibold tracking-tight text-gray-900">
                {{-- Not rounded: the artwork is transparent, so there is no
                     backing shape to round, and the arrowheads reach past the
                     inscribed circle -- a round crop would clip them. --}}
                <img src="{{ asset('favicon.png') }}" alt=""
                     class="h-8 w-8" width="32" height="32">
                {{ config('app.name') }}
            </a>
        </div>

        <div class="flex items-center gap-2">
            {{-- Join a course by class code, from anywhere. Only students see
                 it: enrolment is theirs alone (EduSystem.md Section 7), so for
                 a lecturer or an administrator it would be a button that
                 always 403s. --}}
            @can('course.enroll')
                <a href="{{ route('courses.join') }}"
                   title="Join a course with a class code"
                   class="rounded-full p-2 transition hover:bg-gray-100
                          {{ request()->routeIs('courses.join') ? 'bg-gray-100 text-gray-900' : 'text-gray-600' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="h-6 w-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                </a>
            @endcan

            {{-- The bell belongs where people look for it. Module 3 produces
                 these events; Module 1 owns the inbox (EduSystem.md 2A). --}}
            @php
                $unread = \App\Models\Notification::where('user_id', auth()->id())
                    ->where('is_read', false)->count();
            @endphp
            <a href="{{ route('notifications.index') }}"
               title="{{ $unread ? $unread.' unread' : 'Notifications' }}"
               class="relative rounded-full p-2 transition hover:bg-gray-100
                      {{ request()->routeIs('notifications.*') ? 'bg-gray-100 text-gray-900' : 'text-gray-600' }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="h-6 w-6">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                </svg>
                @if ($unread > 0)
                    <span class="absolute right-1 top-1 min-w-[1.05rem] rounded-full bg-red-600 px-1 text-center text-[10px] font-semibold leading-4 text-white">
                        {{ $unread > 9 ? '9+' : $unread }}
                    </span>
                @endif
            </a>

            <a href="{{ route('profile.edit') }}"
               title="Manage your profile"
               class="flex items-center gap-2 rounded-full py-0.5 pl-0.5 pr-3 text-sm transition hover:bg-gray-100">
                <x-avatar :user="auth()->user()" size="sm" />

                {{-- Name and role together. A browser holds one login at a
                     time, so when three roles are being demonstrated across
                     windows the only thing distinguishing them is this label
                     -- and "which role is this tab?" is otherwise a guess. --}}
                <span class="hidden leading-tight sm:block">
                    <span class="block text-gray-700">{{ auth()->user()->name }}</span>
                    <span class="block text-[11px] uppercase tracking-wide
                        {{ match (auth()->user()->role) {
                            'admin' => 'text-rose-600',
                            'instructor' => 'text-violet-600',
                            default => 'text-emerald-600',
                        } }}">{{ auth()->user()->role }}</span>
                </span>
            </a>

            <form method="post" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                        class="rounded-full px-3 py-2 text-sm text-gray-500 transition hover:bg-gray-100 hover:text-gray-800">
                    Log out
                </button>
            </form>
        </div>
    </header>

    {{-- min-h-0 lets the two panes shrink inside the flex column, which is what
         allows them to scroll rather than pushing the shell taller. Nothing
         here may be items-start: the rail stretches to the full height, so it
         reaches the bottom of the screen instead of stopping under its last
         link. --}}
    <div class="flex min-h-0 flex-1">
        @include('partials.sidebar')

        <div class="min-w-0 flex-1 overflow-y-auto">
            <main class="mx-auto max-w-6xl px-6 py-8">
                @yield('content')
            </main>

            <footer class="mx-auto max-w-6xl px-6 pb-10 text-center text-xs text-gray-400">
                {{ config('app.name') }} &mdash; verify any credential at
                {{ url('/verify') }}/&lbrace;credential id&rbrace;
            </footer>
        </div>
    </div>

    </div>{{-- /viewport shell --}}

    <script>
        // Toggling the rail writes the choice to localStorage, so it is
        // remembered on the next page rather than resetting on every click.
        (function () {
            var button = document.getElementById('sidebar-toggle');

            button.addEventListener('click', function () {
                var closed = document.documentElement.classList.toggle('sidebar-closed');

                localStorage.setItem('learnsync.sidebar', closed ? 'closed' : 'open');
                button.setAttribute('aria-expanded', closed ? 'false' : 'true');
            });

            button.setAttribute('aria-expanded',
                document.documentElement.classList.contains('sidebar-closed') ? 'false' : 'true');
        })();
    </script>

@else

    {{-- Signed out: no rail to show, so the page keeps a simple centred shell.
         This is what a guest verifying a credential sees. --}}
    <header class="border-b border-gray-200 bg-white">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-4">
            <a href="{{ url('/') }}"
               class="flex items-center gap-2 text-lg font-semibold tracking-tight text-gray-900">
                {{-- Not rounded: the artwork is transparent, so there is no
                     backing shape to round, and the arrowheads reach past the
                     inscribed circle -- a round crop would clip them. --}}
                <img src="{{ asset('favicon.png') }}" alt=""
                     class="h-8 w-8" width="32" height="32">
                {{ config('app.name') }}
            </a>
            <a href="{{ route('login') }}" class="text-sm text-gray-600 hover:text-gray-900">Log in</a>
        </div>
    </header>

    <main class="mx-auto max-w-5xl px-4 py-10">
        @yield('content')
    </main>

    <footer class="mx-auto max-w-5xl px-4 pb-10 text-center text-xs text-gray-400">
        {{ config('app.name') }} &mdash; verify any credential at
        {{ url('/verify') }}/&lbrace;credential id&rbrace;
    </footer>

@endauth

</body>
</html>
