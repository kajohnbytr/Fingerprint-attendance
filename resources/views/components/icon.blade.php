@props(['name', 'size' => 18])
@php
    $paths = [
        'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'students' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m20 0v-2a4 4 0 0 0-3-3.87M16 3a4 4 0 0 1 0 8"/><circle cx="9" cy="7" r="4"/>',
        'faculty' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="9" r="3"/><path d="M6.5 19v-2a5.5 5.5 0 0 1 11 0v2"/>',
        'subjects' => '<path d="M12 5v16m0-16C8 2 3 3 2 4v15c4-2 7-1 10 2 3-3 6-4 10-2V4c-4-2-7-1-10 1Z"/>',
        'reports' => '<path d="M5 21V12m7 9V3m7 18V8"/>',
        'announcements' => '<path d="m3 9 18-5v14L3 13V9Zm4 5 2 7h4l-2-6M17 5v12"/>',
        'settings' => '<path d="m9 3-1 3-3 1-2 4 2 2v3l4 3 3-1 3 1 4-3v-3l2-2-2-4-3-1-1-3H9Z"/><circle cx="12" cy="11" r="3"/>',
        'chevron' => '<path d="m8 10 4 4 4-4"/>',
        'search' => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/>',
        'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9m-11 12a2 2 0 0 0 4 0"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4m10-4v4M3 11h18m-14 4h3m4 0h3"/>',
        'folder' => '<path d="M3 7V4h6l3 3h9v13H3V7Z"/>',
        'file' => '<path d="M14 2H5v20h14V7l-5-5v5h5M8 12h8m-8 4h6"/>',
        'key' => '<circle cx="15" cy="8" r="5"/><path d="m11 12-8 8v2h4v-3h3v-3l3-3"/>',
        'check' => '<path d="m5 12 4 4L19 6"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'building' => '<path d="m3 8 9-5 9 5M5 9v11m5-11v11m4-11v11m5-11v11M2 21h20M2 8h20"/>',
    ];
@endphp
<svg {{ $attributes->merge(['class' => 'icon']) }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $paths[$name] ?? $paths['file'] !!}</svg>
