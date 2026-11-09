<div class="nav-section">WORKSPACE</div>

<a class="side-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
    <svg class="nav-icon" viewBox="0 0 20 20" aria-hidden="true"><rect x="2.5" y="2.5" width="6" height="6" rx=".7"/><rect x="11.5" y="2.5" width="6" height="6" rx=".7"/><rect x="2.5" y="11.5" width="6" height="6" rx=".7"/><rect x="11.5" y="11.5" width="6" height="6" rx=".7"/></svg>
    <span>Dashboard</span>
</a>

@if(auth()->user()->hasPermission('tickets.view_all') || auth()->user()->hasPermission('tickets.view_assigned') || auth()->user()->hasPermission('tickets.view_own'))
    <a class="side-link {{ request()->routeIs('tickets.index', 'tickets.show', 'tickets.edit') ? 'active' : '' }}" href="{{ route('tickets.index') }}">
        <svg class="nav-icon" viewBox="0 0 20 20" aria-hidden="true"><path d="M5 2.5h7l3.5 3.5v11.5H5z"/><path d="M12 2.5V6h3.5M7.5 9h5M7.5 12h5M7.5 15h3"/></svg>
        <span>Tickets</span>
    </a>
@endif

@if(auth()->user()->hasPermission('tickets.create'))
    <a class="side-link {{ request()->routeIs('tickets.create') ? 'active' : '' }}" href="{{ route('tickets.create') }}">
        <svg class="nav-icon" viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="7.2"/><path d="M10 6.5v7M6.5 10h7"/></svg>
        <span>Submit a request</span>
    </a>
@endif

@if(auth()->user()->hasPermission('knowledge.view') || auth()->user()->hasPermission('knowledge.manage'))
    <a class="side-link {{ request()->routeIs('knowledge.*') ? 'active' : '' }}" href="{{ route('knowledge.index') }}">
        <svg class="nav-icon" viewBox="0 0 20 20" aria-hidden="true"><path d="M2.5 4.5c2.7-.8 5.2-.4 7.5 1.1v11c-2.3-1.5-4.8-1.9-7.5-1.1zM17.5 4.5c-2.7-.8-5.2-.4-7.5 1.1v11c2.3-1.5 4.8-1.9 7.5-1.1z"/><path d="M5 7.5h2M5 10h2M13 7.5h2M13 10h2"/></svg>
        <span>Knowledge base</span>
    </a>
@endif

@if(auth()->user()->hasPermission('reports.view'))
    <div class="nav-section mt-3">REPORTS</div>
    <a class="side-link {{ request()->routeIs('reports.tickets*') ? 'active' : '' }}" href="{{ route('reports.tickets') }}">
        <svg class="nav-icon" viewBox="0 0 20 20" aria-hidden="true"><path d="M3 17.5V10h4v7.5M8 17.5V5h4v12.5M13 17.5V8h4v9.5M2 17.5h16"/></svg>
        <span>Ticket reports</span>
    </a>
    <a class="side-link {{ request()->routeIs('reports.assets*') ? 'active' : '' }}" href="{{ route('reports.assets') }}">
        <svg class="nav-icon" viewBox="0 0 20 20" aria-hidden="true"><path d="m10 2.5 7 3.7v7.6l-7 3.7-7-3.7V6.2z"/><path d="m3.2 6.3 6.8 3.6 6.8-3.6M10 10v7.2M6.5 4.4l7 3.8"/></svg>
        <span>Asset report</span>
    </a>
    <a class="side-link {{ request()->routeIs('reports.maintenance*') ? 'active' : '' }}" href="{{ route('reports.maintenance') }}">
        <svg class="nav-icon" viewBox="0 0 20 20" aria-hidden="true"><path d="M12.6 3.1a4.2 4.2 0 0 0-5.4 5.4l-4.1 4.1a1.8 1.8 0 0 0 2.5 2.5l4.1-4.1a4.2 4.2 0 0 0 5.4-5.4l-2.4 2.4-2.5-.5-.5-2.5z"/></svg>
        <span>Maintenance report</span>
    </a>
@endif

@if(auth()->user()->isMisStaff())
    <a class="side-link {{ request()->routeIs('maintenance.*') ? 'active' : '' }}" href="{{ route('maintenance.index') }}">
        <svg class="nav-icon" viewBox="0 0 20 20" aria-hidden="true"><path d="M4 16.5h12M5 14.5V8h10v6.5M7 8V4.5h6V8M7.5 11h1M11.5 11h1"/><path d="M3 16.5h14"/></svg>
        <span>Maintenance</span>
    </a>
@endif

@if(auth()->user()->isRole('system_administrator'))
    <div class="nav-section mt-4">ADMINISTRATION</div>
    <a class="side-link {{ request()->routeIs('departments.*') ? 'active' : '' }}" href="{{ route('departments.index') }}">
        <svg class="nav-icon" viewBox="0 0 20 20" aria-hidden="true"><path d="M3 17.5V3.5h9v14M12 7h5v10.5M1.5 17.5h17"/><path d="M5.5 6h1M9 6h1M5.5 9h1M9 9h1M5.5 12h1M9 12h1M14 10h1M14 13h1"/></svg>
        <span>Departments</span>
    </a>
    <a class="side-link {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}">
        <svg class="nav-icon" viewBox="0 0 20 20" aria-hidden="true"><circle cx="8" cy="6.3" r="3"/><path d="M2.5 16.5v-1.3a5.5 5.5 0 0 1 11 0v1.3zM14 4.1a2.7 2.7 0 0 1 0 5.2M15 11.4a4 4 0 0 1 2.5 3.7v1.4"/></svg>
        <span>User accounts</span>
    </a>
    <a class="side-link {{ request()->routeIs('assets.*') ? 'active' : '' }}" href="{{ route('assets.index') }}">
        <svg class="nav-icon" viewBox="0 0 20 20" aria-hidden="true"><rect x="3" y="3" width="14" height="10" rx="1"/><path d="M1.8 16.5h16.4M7 16.5l.7-3.5h4.6l.7 3.5"/></svg>
        <span>IT assets</span>
    </a>
    <a class="side-link {{ request()->routeIs('settings.*') ? 'active' : '' }}" href="{{ route('settings.index') }}">
        <svg class="nav-icon" viewBox="0 0 20 20" aria-hidden="true"><path d="M8.2 2.5h3.6l.5 2a5.8 5.8 0 0 1 1.4.8l1.9-.7 1.8 3.1-1.4 1.5a5.7 5.7 0 0 1 0 1.6l1.4 1.5-1.8 3.1-1.9-.7a5.8 5.8 0 0 1-1.4.8l-.5 2H8.2l-.5-2a5.8 5.8 0 0 1-1.4-.8l-1.9.7-1.8-3.1L4 10.8a5.7 5.7 0 0 1 0-1.6L2.6 7.7l1.8-3.1 1.9.7a5.8 5.8 0 0 1 1.4-.8z"/><circle cx="10" cy="10" r="2.2"/></svg>
        <span>System settings</span>
    </a>
    <a class="side-link {{ request()->routeIs('categories.*', 'subcategories.*') ? 'active' : '' }}" href="{{ route('categories.index') }}">
        <svg class="nav-icon" viewBox="0 0 20 20" aria-hidden="true"><path d="M2.5 5.5h6l1.5 1.7h7.5v8.3h-15z"/><path d="M2.5 5.5V4h6l1.5 1.5"/></svg>
        <span>Ticket categories</span>
    </a>
    <a class="side-link {{ request()->routeIs('roles.*') ? 'active' : '' }}" href="{{ route('roles.index') }}">
        <svg class="nav-icon" viewBox="0 0 20 20" aria-hidden="true"><path d="M10 2.5 16 5v4.2c0 4-2.5 6.7-6 8.3-3.5-1.6-6-4.3-6-8.3V5z"/><path d="m7 9.8 2 2 4-4"/></svg>
        <span>Roles &amp; permissions</span>
    </a>
    <a class="side-link {{ request()->routeIs('audit.*') ? 'active' : '' }}" href="{{ route('audit.index') }}">
        <svg class="nav-icon" viewBox="0 0 20 20" aria-hidden="true"><rect x="4" y="3.5" width="12" height="14" rx="1"/><path d="M7 3.5V2.5h6v1M7 7.5h6M7 10.5h6M7 13.5h4"/></svg>
        <span>Audit log</span>
    </a>
@endif

<div class="sidebar-foot"><div class="small fw-semibold">{{ $universityName }} MIS Office</div><div class="small text-muted">Service requests and support</div></div>
