<!-- layout.blade.php -->
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>

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
        .sidebar-closed .sidebar-group-title,
        .sidebar-closed .sidebar-badge { display: none; }

        .sidebar-closed .sidebar-divider { display: block; }

        .sidebar-closed .sidebar-dot { display: block; }

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

    {{-- TOP BAR: menu toggle, brand, and the account controls only.
         Everything else lives in the rail. --}}
    <header class="sticky top-0 z-20 flex items-center justify-between gap-3 border-b border-gray-200 bg-white px-4 py-2.5">
        <div class="flex items-center gap-3">
            <button type="button" id="sidebar-toggle"
                    title="Main menu" aria-label="Toggle the main menu" aria-expanded="true"
                    class="rounded-full p-2 text-gray-600 transition hover:bg-gray-100">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-6 w-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                </svg>
            </button>

            <a href="{{ route('dashboard') }}" class="text-lg font-semibold tracking-tight text-gray-900">
                {{ config('app.name') }}
            </a>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('profile.edit') }}"
               title="Manage your profile"
               class="flex items-center gap-2 rounded-full py-0.5 pl-0.5 pr-3 text-sm transition hover:bg-gray-100">
                <x-avatar :user="auth()->user()" size="sm" />
                <span class="hidden text-gray-600 sm:inline">{{ auth()->user()->name }}</span>
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

    <div class="flex items-start">
        @include('partials.sidebar')

        <div class="min-w-0 flex-1">
            <main class="mx-auto max-w-6xl px-6 py-8">
                @yield('content')
            </main>

            <footer class="mx-auto max-w-6xl px-6 pb-10 text-center text-xs text-gray-400">
                {{ config('app.name') }} &mdash; verify any credential at
                {{ url('/verify') }}/&lbrace;credential id&rbrace;
            </footer>
        </div>
    </div>

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
            <a href="{{ url('/') }}" class="text-lg font-semibold tracking-tight text-gray-900">
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
