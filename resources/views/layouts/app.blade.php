<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title','Dashboard') · {{ $universityName }} MIS Helpdesk</title>
    <link rel="icon" type="image/png" href="{{ asset('images/city-of-ilagan-college-logo.png') }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <link href="{{ asset('css/modules.css') }}" rel="stylesheet">
    <link href="{{ asset('css/notifications.css') }}" rel="stylesheet">
    @stack('head')
</head>
<body>
@php($unreadNotificationCount = auth()->user()->unreadNotifications()->count())
<nav class="navbar navbar-expand-lg topbar" aria-label="Main navigation">
    <div class="container-fluid px-3 px-lg-4">
        <button class="btn btn-outline-light d-lg-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileNav" aria-controls="mobileNav" aria-label="Open navigation">
            <svg width="18" height="18" viewBox="0 0 20 20" aria-hidden="true"><path d="M3 5h14M3 10h14M3 15h14" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
        </button>
        <a class="navbar-brand text-white d-flex align-items-center gap-2" href="{{ route('dashboard') }}">
            <img src="{{ asset('images/city-of-ilagan-college-logo.png') }}" alt="City of Ilagan College logo" class="institution-logo">
            <span><strong>{{ $universityName }}</strong><small class="d-block brand-sub">MIS Helpdesk</small></span>
        </a>

        <div class="ms-auto d-flex align-items-center gap-2 gap-sm-3 topbar-actions">
            <a class="notification-link" href="{{ route('notifications.index') }}" aria-label="Notifications{{ $unreadNotificationCount ? ', '.$unreadNotificationCount.' unread' : '' }}" title="Notifications">
                <svg width="17" height="17" viewBox="0 0 20 20" aria-hidden="true"><path d="M15.5 7.8a5.5 5.5 0 0 0-11 0c0 6-2 6-2 7.2h15c0-1.2-2-1.2-2-7.2ZM8 17h4" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span class="notification-label">Notifications</span>
                @if($unreadNotificationCount)<span class="notification-count">{{ $unreadNotificationCount }}</span>@endif
            </a>
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-light profile-button dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Account menu for {{ auth()->user()->name }}">
                    <span class="profile-avatar" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr(auth()->user()->first_name,0,1)) }}</span>
                    <span class="profile-name">{{ auth()->user()->name }}</span>
                </button>
                <div class="dropdown-menu dropdown-menu-end profile-menu">
                    <div class="px-3 py-2">
                        <div class="fw-semibold">{{ auth()->user()->name }}</div>
                        <div class="small text-muted text-break">{{ auth()->user()->email }}</div>
                        <span class="role-badge mt-2">{{ auth()->user()->role->label }}</span>
                    </div>
                    <div class="dropdown-divider"></div>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button class="dropdown-item text-danger" type="submit">Sign out</button></form>
                </div>
            </div>
        </div>
    </div>
</nav>

<div class="offcanvas offcanvas-start mobile-drawer" tabindex="-1" id="mobileNav" aria-labelledby="mobileNavTitle">
    <div class="offcanvas-header"><h2 class="h5 mb-0" id="mobileNavTitle">Navigation</h2><button class="btn-close" type="button" data-bs-dismiss="offcanvas" aria-label="Close navigation"></button></div>
    <div class="offcanvas-body p-0">@include('layouts.navigation')</div>
</div>

<div class="app-shell">
    <aside class="sidebar d-none d-lg-block" aria-label="Sidebar navigation">@include('layouts.navigation')</aside>
    <main class="main-content">
        <div class="page-heading"><div><div class="eyebrow">MIS OFFICE</div><h1>@yield('heading','Dashboard')</h1></div>@yield('actions')</div>
        @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger" role="alert"><strong>Please review the following:</strong><ul class="mb-0 mt-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @yield('content')
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
