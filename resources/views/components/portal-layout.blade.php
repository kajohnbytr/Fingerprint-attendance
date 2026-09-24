@props(['page'])
<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#181818"><title>{{ $page === 'dashboard' ? 'Dashboard' : ucwords(str_replace(['students.', '-'], ['', ' '], $page)) }} · Northbridge</title>{{ Vite::fonts() }}@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body data-page="{{ $page }}">
<button class="sidebar-scrim" aria-label="Close navigation" hidden></button>
<aside class="sidebar" id="sidebar">
    <a class="brand" href="{{ route('dashboard') }}"><span class="brand-mark"><x-icon name="building" size="24" /></span><span><strong>Northbridge</strong><small>PROGRAM HEAD PORTAL</small></span></a>
    <div class="workspace-label">WORKSPACE</div>
    <nav aria-label="Main navigation">
        <a class="nav-item {{ $page === 'dashboard' ? 'active' : '' }}" href="{{ route('dashboard') }}" @if($page === 'dashboard') aria-current="page" @endif><x-icon name="dashboard" />Dashboard @if($page === 'dashboard')<span class="nav-dot"></span>@endif</a>
        <button class="nav-item students-toggle {{ str_starts_with($page, 'students.') ? 'parent-active' : '' }}" aria-expanded="{{ str_starts_with($page, 'students.') ? 'true' : 'false' }}" aria-controls="student-submenu"><x-icon name="students" />Students<x-icon name="chevron" class="submenu-arrow" /></button>
        <div class="submenu" id="student-submenu" @if(!str_starts_with($page, 'students.')) hidden @endif>
            @foreach(['schedule' => 'Schedule', 'blocks' => 'Blocks', 'enrolled' => 'Enrolled Students'] as $key => $label)
                <a class="{{ $page === 'students.'.$key ? 'active' : '' }}" href="{{ route('students.'.$key) }}" @if($page === 'students.'.$key) aria-current="page" @endif>{{ $label }} @if($page === 'students.'.$key)<span class="nav-dot"></span>@endif</a>
            @endforeach
        </div>
        @foreach(['faculty' => 'Faculty', 'subjects' => 'Subjects', 'reports' => 'Reports', 'announcements' => 'Announcements', 'settings' => 'Settings'] as $key => $label)
            <a class="nav-item {{ $page === $key ? 'active' : '' }}" href="{{ route($key) }}" @if($page === $key) aria-current="page" @endif><x-icon :name="$key" />{{ $label }} @if($page === $key)<span class="nav-dot"></span>@endif</a>
        @endforeach
    </nav>
    <div class="help-card"><strong>Need assistance?</strong><p>Contact campus IT for portal and access support.</p><a href="mailto:support@northbridge.example">Get in touch <span>↗</span></a></div>
    <div class="sidebar-footer"><span class="status-dot"></span> Academic year 2026–2027</div>
</aside>
<div class="main-shell">
    <header class="topbar"><button class="icon-button mobile-menu" aria-label="Open navigation" aria-expanded="false" aria-controls="sidebar"><x-icon name="menu" /></button><div class="greeting"><h1>Good morning, Dr. Morgan</h1><p>Thursday, September 10 · Fall semester</p></div><div class="header-actions"><form class="global-search" action="{{ route('students.enrolled') }}"><x-icon name="search" size="16" /><input name="q" aria-label="Search students" placeholder="Search students…"><kbd>/</kbd></form><a class="icon-button notification-button" aria-label="View announcements" href="{{ route('announcements') }}"><x-icon name="bell" /><i></i></a><a class="profile" href="{{ route('settings') }}"><span class="avatar">EM</span><span><strong>Elena Morgan</strong><small>PROGRAM HEAD</small></span><x-icon name="chevron" size="14" /></a></div></header>
    <main id="main-content">{{ $slot }}</main>
    <footer class="page-footer"><span>Northbridge University</span><span>Program Head Portal <span class="footer-separator">/</span> Fall 2026</span></footer>
</div>
<div class="toast" role="status" hidden></div>
</body></html>
