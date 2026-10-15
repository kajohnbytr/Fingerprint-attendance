<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <meta
        name="csrf-token"
        content="{{ csrf_token() }}"
    >

    <title>
        @yield('title', 'Program Head Portal')
    </title>

    @vite([
        'resources/css/program_head/portal.css',
        'resources/js/app.js'
    ])
</head>

<body class="portal-page role-{{ auth()->user()->role }}">
<div class="scroll-progress" aria-hidden="true"><span></span></div>
<div class="shell">

    <aside class="sidebar" id="portal-sidebar">

        {{-- Brand --}}
        <a
            class="brand"
            href="{{ route('dashboard') }}"
        >
            <span class="brand-logo" aria-hidden="true">
                <svg viewBox="0 0 24 24" focusable="false">
                    <path d="M12 3.5 18.5 6v5.2c0 4.3-2.7 7.9-6.5 9.3-3.8-1.4-6.5-5-6.5-9.3V6L12 3.5Z"></path>
                </svg>
            </span>

            <span>
                @if(in_array(auth()->user()->role, ['admin', 'program_head', 'faculty'], true))
                    {{ [
                        'admin' => 'System Admin',
                        'program_head' => 'Program Head',
                        'faculty' => 'Faculty',
                    ][auth()->user()->role] }}
                    <small>{{ strtoupper(str_replace('_', ' ', auth()->user()->role)) }} PORTAL</small>
                @else
                    Northbridge
                    <small>{{ ['student' => 'STUDENT PORTAL'][auth()->user()->role] ?? 'PORTAL' }}</small>
                @endif
            </span>
        </a>

        <p class="nav-label">WORKSPACE</p>

        <nav>

            @if(auth()->user()->role === 'admin')
                @foreach(['dashboard' => ['&#9638;', 'Dashboard'], 'attendance' => ['&#10003;', 'Attendance'], 'students' => ['&#9817;', 'Students'], 'schedules' => ['&#9707;', 'Schedules'], 'inbox' => ['&#9993;', 'Announcements & Complaints'], 'reports' => ['&#9637;', 'Reports'], 'users' => ['&#9823;', 'Users & Roles'], 'settings' => ['&#9881;', 'Settings']] as $page => [$icon, $label])
                    <a href="{{ route('admin.'.$page) }}" title="{{ $label }}" aria-label="{{ $label }}" class="nav-link {{ request()->routeIs('admin.'.$page, 'admin.'.$page.'.*') || ($page === 'inbox' && request()->routeIs('admin.announcements.*', 'admin.complaints.*')) ? 'active' : '' }}"><span aria-hidden="true">{!! $icon !!}</span><span>{{ $label }}</span></a>
                @endforeach
            @elseif(auth()->user()->role === 'student')
                <a class="nav-link active" href="{{ route('dashboard') }}" aria-label="My profile"><span aria-hidden="true">&#9817;</span><span>My profile</span></a>
            @elseif(auth()->user()->role === 'faculty')
                @foreach(['dashboard' => ['&#9638;', 'Dashboard'], 'attendance' => ['&#10003;', 'Attendance'], 'students' => ['&#9817;', 'Enrolled Students'], 'schedules' => ['&#9707;', 'Class Schedules'], 'reports' => ['&#9637;', 'Reports'], 'inbox' => ['&#9993;', 'Announcements & Complaints'], 'settings' => ['&#9881;', 'Settings']] as $page => [$icon, $label])
                    <a href="{{ route('faculty.'.$page) }}" title="{{ $label }}" aria-label="{{ $label }}" class="nav-link {{ request()->routeIs('faculty.'.$page, 'faculty.'.$page.'.*') ? 'active' : '' }}">
                        <span aria-hidden="true">{!! $icon !!}</span><span>{{ $label }}</span>
                    </a>
                @endforeach
            @else

            {{-- Dashboard --}}
            <a
                href="{{ route('dashboard') }}"
                class="nav-link
                    {{ request()->routeIs('dashboard')
                        ? 'active'
                        : '' }}"
            >
                <span>▦</span>
                <span>Dashboard</span>
            </a>

            {{-- Program Head and Administrator navigation --}}
            @if(in_array(
                auth()->user()->role,
                ['admin', 'program_head'],
                true
            ))

                {{-- Student navigation --}}
                <details
                    class="nav-group"
                    {{ request()->routeIs(
                        'students.*',
                        'blocks.*',
                        'schedules.*'
                    ) ? 'open' : '' }}
                >
                    <summary>
                        <span>♙</span>
                        <span>Students</span>
                        <b>⌄</b>
                    </summary>

                    <a
                        href="{{ route('schedules.index') }}"
                        class="sub-link
                            {{ request()->routeIs(
                                'schedules.*'
                            ) ? 'active' : '' }}"
                    >
                        Schedule
                    </a>

                    <a
                        href="{{ route('blocks.index') }}"
                        class="sub-link
                            {{ request()->routeIs(
                                'blocks.*'
                            ) ? 'active' : '' }}"
                    >
                        Blocks
                    </a>

                    <a
                        href="{{ route('students.index') }}"
                        class="sub-link
                            {{ request()->routeIs(
                                'students.*'
                            ) ? 'active' : '' }}"
                    >
                        Enrolled Students
                    </a>
                </details>

                {{-- Subject assignment --}}
                <a
                    href="{{ route(
                        'program-head.subjects.index'
                    ) }}"
                    class="nav-link
                        {{ request()->routeIs(
                            'program-head.subjects.*'
                        ) ? 'active' : '' }}"
                >
                    <span>▣</span>
                    <span>Subjects</span>
                </a>

                {{-- Faculty teaching loads --}}
                <a
                    href="{{ route(
                        'program-head.faculty.index'
                    ) }}"
                    class="nav-link
                        {{ request()->routeIs(
                            'program-head.faculty.*',
                            'program-head.faculty-loads.*'
                        ) ? 'active' : '' }}"
                >
                    <span>◎</span>
                    <span>Faculty</span>
                </a>

            @endif

            {{-- Faculty account navigation --}}
            @if(auth()->user()->role === 'faculty')
                <a
                    href="{{ route(
                        'program-head.faculty-loads.index'
                    ) }}"
                    class="nav-link
                        {{ request()->routeIs(
                            'program-head.faculty-loads.*'
                        ) ? 'active' : '' }}"
                >
                    <span>◎</span>
                    <span>Teaching Load</span>
                </a>
            @endif

            <a class="nav-link {{ request()->routeIs('program-head.reports*') ? 'active' : '' }}" href="{{ route('program-head.reports') }}">
                <span>▥</span>
                <span>Reports</span>
            </a>

            <a class="nav-link {{ request()->routeIs('program-head.announcements*') ? 'active' : '' }}" href="{{ route('program-head.announcements') }}">
                <span>♧</span>
                <span>Announcements</span>
            </a>

            <a class="nav-link {{ request()->routeIs('program-head.settings*') ? 'active' : '' }}" href="{{ route('program-head.settings') }}">
                <span>⚙</span>
                <span>Settings</span>
            </a>

            @endif
        </nav>

        @if(auth()->user()->role === 'student')
            <div class="help-card">
                <strong>Need assistance?</strong>

                <small>
                    Contact campus IT for portal and access support.
                </small>
            </div>
        @endif

    </aside>

    <main class="main">

        <header class="topbar">

            <button class="mobile-menu chrome-button" type="button" aria-label="Open navigation" aria-controls="portal-sidebar" aria-expanded="false">☰</button>

            <div class="topbar-context">
                <strong>
                    @yield(
                        'heading',
                        'Welcome Back, '.auth()->user()->name
                    )
                </strong>

                <small>
                    @yield(
                        'subheading',
                        'Monitor university operations and recent changes.'
                    )
                </small>
            </div>

            @if(in_array(auth()->user()->role, ['admin', 'program_head', 'faculty'], true))
                <form class="header-search global-search" action="{{ route('students.index') }}" method="GET" role="search" data-search-url="{{ route('portal.search') }}">
                    <span aria-hidden="true">⌕</span>
                    <input
                        id="global-search"
                        type="search"
                        name="search"
                        value="{{ request('search') }}"
                        placeholder="Search students, faculty, subjects"
                        aria-label="Search students, faculty, and subjects"
                        aria-controls="global-search-results"
                        aria-expanded="false"
                        autocomplete="off"
                        minlength="2"
                    >
                    <kbd>/</kbd>
                    <div class="global-search-results" id="global-search-results" role="listbox" hidden></div>
                </form>
            @endif

            <div class="profile">

                @if(in_array(auth()->user()->role, ['admin', 'program_head', 'faculty'], true))
                    <div class="portal-notifications" data-notifications-url="{{ route('portal.notifications') }}">
                        @php
                            $inboxRoute = match (auth()->user()->role) {
                                'admin' => route('admin.inbox'),
                                'faculty' => route('faculty.inbox'),
                                default => route('program-head.inbox'),
                            };
                        @endphp
                        <button class="header-icon notification-toggle" type="button" aria-label="Notifications" aria-controls="notification-menu" aria-expanded="false">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>
                            <span class="notification-count" hidden></span>
                        </button>
                        <section class="notification-menu" id="notification-menu" aria-label="Recent notifications" hidden>
                            <header><strong>Recent updates</strong><a href="{{ $inboxRoute }}">View inbox</a></header>
                            <div class="notification-items" aria-live="polite"><p>Loading updates…</p></div>
                        </section>
                    </div>
                @endif

                <span class="avatar">
                    {{
                        strtoupper(
                            substr(
                                auth()->user()->name,
                                0,
                                1
                            )
                        )
                    }}
                </span>

                <span>
                    {{ auth()->user()->name }}

                    <small>
                        {{
                            strtoupper(
                                str_replace(
                                    '_',
                                    ' ',
                                    auth()->user()->role
                                )
                            )
                        }}
                    </small>
                </span>

                <form
                    method="POST"
                    action="{{ route('logout') }}"
                >
                    @csrf

                    <button
                        class="logout-button"
                        type="submit"
                    >
                        Logout
                    </button>
                </form>

            </div>

        </header>

        <section class="content">

            {{-- Success messages --}}
            @if(session('success'))
                <div class="alert success">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Validation errors --}}
            @if($errors->any())
                <div class="alert error">
                    <strong>
                        Please correct the following:
                    </strong>

                    <ul>
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')

            <footer class="portal-footer">
                <small>Last site update: {{ now()->format('M j, Y') }}</small>
            </footer>

        </section>

    </main>

</div>
<button class="back-to-top" type="button" aria-label="Back to top" title="Back to top">↑</button>
<div class="cookie-banner" role="region" aria-label="Cookie notice" hidden>
    <div><strong>Your privacy matters</strong><p>We use essential cookies for secure authentication and saved display preferences.</p></div>
    <button class="button primary cookie-accept" type="button">Got it</button>
</div>
<dialog class="confirm-dialog" aria-labelledby="confirm-title" aria-describedby="confirm-message">
    <form method="dialog">
        <h2 id="confirm-title">Confirm action</h2>
        <p id="confirm-message">This action cannot be undone.</p>
        <div class="confirm-actions">
            <button class="button confirm-cancel" value="cancel">Cancel</button>
            <button class="button danger confirm-continue" value="confirm">Continue</button>
        </div>
    </form>
</dialog>
@php($idleTimeoutSettings = app(\App\Security\IdleTimeout::class)->browserSettings())
<div class="idle-warning" role="dialog" aria-modal="true" aria-labelledby="idle-warning-title" data-warning-delay-seconds="{{ $idleTimeoutSettings['warningDelaySeconds'] }}" data-countdown-seconds="{{ $idleTimeoutSettings['countdownSeconds'] }}" hidden>
    <div class="idle-warning-card">
        <span class="idle-warning-icon" aria-hidden="true">!</span>
        <h2 id="idle-warning-title">Are you still there?</h2>
        <p>No activity was detected. For security, you will be signed out automatically in <strong class="idle-countdown">{{ sprintf('%d:%02d', intdiv($idleTimeoutSettings['countdownSeconds'], 60), $idleTimeoutSettings['countdownSeconds'] % 60) }}</strong>.</p>
        <button class="button idle-stay-signed-in" type="button">I’m still here</button>
    </div>
</div>
<div class="page-loader" aria-hidden="true"><span></span></div>
</body>
</html>
