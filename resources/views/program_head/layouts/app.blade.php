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
        @yield('title', 'Northbridge Portal')
    </title>

    @vite([
        'resources/css/program_head/portal.css',
        'resources/js/app.js'
    ])
</head>

<body>
<div class="shell">

    <aside class="sidebar">

        {{-- Brand --}}
        <a
            class="brand"
            href="{{ route('dashboard') }}"
        >
            <span class="brand-logo">N</span>

            <span>
                Northbridge

                <small>
                    {{ ['admin' => 'SYSTEM ADMIN PORTAL', 'faculty' => 'FACULTY PORTAL', 'student' => 'STUDENT PORTAL'][auth()->user()->role] ?? 'PROGRAM HEAD PORTAL' }}
                </small>
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
                        'program-head.faculty-loads.index'
                    ) }}"
                    class="nav-link
                        {{ request()->routeIs(
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

            {{-- Placeholder navigation --}}
            <a class="nav-link" href="#">
                <span>▥</span>
                <span>Reports</span>
            </a>

            <a class="nav-link" href="#">
                <span>♧</span>
                <span>Announcements</span>
            </a>

            <a class="nav-link" href="#">
                <span>⚙</span>
                <span>Settings</span>
            </a>

            @endif
        </nav>

        <div class="help-card">
            <strong>Need assistance?</strong>

            <small>
                Contact campus IT for portal and access support.
            </small>
        </div>

    </aside>

    <main class="main">

        <header class="topbar">

            <div>
                <strong>
                    @yield(
                        'heading',
                        'Good morning, '.auth()->user()->name
                    )
                </strong>

                <small>
                    @yield(
                        'subheading',
                        'Monitor university operations and recent changes.'
                    )
                </small>
            </div>

            <div class="profile">

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

        </section>

    </main>

</div>
</body>
</html>
