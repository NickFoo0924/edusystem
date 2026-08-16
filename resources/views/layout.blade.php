<!-- layout.blade.php -->
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name'))</title>
    {{-- Page data for scripts must be defined before the bundle runs. --}}
    @stack('scripts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gray-100 font-sans text-gray-900 antialiased">

    <nav class="border-b border-gray-200 bg-white">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-4">
            <a href="{{ url('/') }}" class="text-lg font-semibold tracking-tight text-gray-900">
                {{ config('app.name') }}
            </a>

            <div class="flex flex-wrap items-center gap-4 text-sm">
                @auth
                    @php
                        // Module 1 owns the inbox (EduSystem.md Section 2A).
                        $unread = \App\Models\Notification::where('user_id', auth()->id())
                            ->where('is_read', false)->count();
                    @endphp

                    <a href="{{ route('dashboard') }}" class="text-gray-600 hover:text-gray-900">Dashboard</a>
                    <a href="{{ route('courses.index') }}" class="text-gray-600 hover:text-gray-900">Courses</a>
                    <a href="{{ route('announcements.index') }}" class="text-gray-600 hover:text-gray-900">News</a>

                    {{-- Every item is gated on a permission key, so the menu
                         reshapes itself when the matrix is edited. --}}
                    @can('certificate.view_own')
                        <a href="{{ route('certificates.index') }}" class="text-gray-600 hover:text-gray-900">
                            Certificates
                        </a>
                    @endcan
                    @can('progress.view_own')
                        <a href="{{ route('badges.cabinet') }}" class="text-gray-600 hover:text-gray-900">Trophies</a>
                    @endcan
                    @can('certificate.revoke')
                        <a href="{{ route('admin.certificates.index') }}" class="text-gray-600 hover:text-gray-900">
                            Credentials
                        </a>
                    @endcan
                    @can('learningpath.manage')
                        <a href="{{ route('learning-paths.index') }}" class="text-gray-600 hover:text-gray-900">Paths</a>
                    @endcan
                    @can('badge.manage')
                        <a href="{{ route('badges.index') }}" class="text-gray-600 hover:text-gray-900">Badges</a>
                    @endcan
                    @can('user.activate')
                        <a href="{{ route('users.index') }}" class="text-gray-600 hover:text-gray-900">Accounts</a>
                    @endcan
                    @can('invitation.issue')
                        <a href="{{ route('invitations.index') }}" class="text-gray-600 hover:text-gray-900">Invites</a>
                    @endcan
                    @can('activitylog.view')
                        <a href="{{ route('activity-logs.index') }}" class="text-gray-600 hover:text-gray-900">Activity</a>
                    @endcan
                    @can('permission.manage')
                        <a href="{{ route('permissions.index') }}" class="text-gray-600 hover:text-gray-900">
                            Permissions
                        </a>
                    @endcan

                    {{-- The bell, with its unread count. --}}
                    <a href="{{ route('notifications.index') }}" class="relative text-gray-600 hover:text-gray-900"
                       title="{{ $unread }} unread">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                        </svg>
                        @if ($unread > 0)
                            <span class="absolute -right-2 -top-1.5 rounded-full bg-red-600 px-1.5 text-[10px] font-semibold text-white">
                                {{ $unread > 9 ? '9+' : $unread }}
                            </span>
                        @endif
                    </a>

                    {{-- Avatar and name both open the profile page, so the
                         obvious thing to click does the obvious thing. --}}
                    <a href="{{ route('profile.edit') }}"
                       title="Manage your profile"
                       class="flex items-center gap-2 rounded-full py-0.5 pl-0.5 pr-3 transition hover:bg-gray-100">
                        <x-avatar :user="auth()->user()" size="sm" />
                        <span class="hidden text-gray-600 sm:inline">{{ auth()->user()->name }}</span>
                    </a>

                    <form method="post" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-gray-400 hover:text-gray-700">Log out</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="text-gray-600 hover:text-gray-900">Log in</a>
                @endauth
            </div>
        </div>
    </nav>

    <main class="mx-auto max-w-6xl px-4 py-10">
        @yield('content')
    </main>

    <footer class="mx-auto max-w-6xl px-4 pb-10 text-center text-xs text-gray-400">
        {{ config('app.name') }} &mdash; verify any credential at {{ url('/verify') }}/&lbrace;credential id&rbrace;
    </footer>

</body>
</html>
