@extends('layouts.app')

@section('title', 'User accounts')
@section('heading', 'User accounts')

@section('actions')
    <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#createAccountModal">
        <svg class="me-1" width="15" height="15" viewBox="0 0 20 20" aria-hidden="true"><path d="M10 4v12M4 10h12" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        Create account
    </button>
@endsection

@push('head')
    <style>
        .account-page { --account-border: #e3e8ee; }
        .account-directory .panel-header { gap: 16px; }
        .account-directory-heading { align-items: flex-start; }
        .account-count { display: inline-flex; align-items: center; gap: 7px; margin-top: 7px; color: #718096; font-size: 11px; }
        .account-count strong { color: #253247; font-weight: 650; }
        .account-count-dot { width: 6px; height: 6px; border-radius: 50%; background: #c5a24a; }
        .account-filters { padding: 0 20px 17px; }
        .account-filters .form-control, .account-filters .form-select { min-height: 39px; }
        .account-import { margin-bottom: 16px; border: 1px solid var(--account-border); border-radius: 4px; background: #fff; }
        .account-import summary { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 13px 17px; color: #315a83; cursor: pointer; font-size: 12px; font-weight: 650; list-style: none; }
        .account-import summary::-webkit-details-marker { display: none; }
        .account-import summary::after { content: '+'; color: #718096; font-size: 18px; font-weight: 400; }
        .account-import[open] summary::after { content: '\2212'; }
        .account-import-body { padding: 0 17px 16px; border-top: 1px solid #edf0f3; }
        .account-import-body p { margin: 12px 0; color: #718096; font-size: 11px; line-height: 1.5; }
        .account-import-controls { display: flex; gap: 9px; align-items: center; }
        .account-import-controls .form-control { max-width: 460px; }
        .account-table-wrap { overflow: visible; }
        .account-table { min-width: 850px; }
        .account-table th:last-child, .account-table td:last-child { width: 155px; }
        .account-cell { min-width: 235px; }
        .account-person { display: flex; align-items: center; gap: 10px; }
        .account-monogram { display: inline-flex; width: 34px; height: 34px; flex: 0 0 34px; align-items: center; justify-content: center; border: 1px solid #dce4ec; border-radius: 50%; background: #f1f5f9; color: #315a83; font-size: 10px; font-weight: 700; }
        .account-person-name { color: #253247; font-size: 12px; font-weight: 650; line-height: 1.35; }
        .account-person-meta { margin-top: 3px; color: #718096; font-size: 10px; line-height: 1.45; overflow-wrap: anywhere; }
        .account-secondary { color: #718096; font-size: 11px; }
        .account-role { display: inline-block; max-width: 170px; padding: 4px 7px; border: 1px solid #e1e7ee; background: #f7f9fb; color: #42536a; font-size: 10px; line-height: 1.35; }
        .account-status { display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; font-size: 10px; font-weight: 650; }
        .account-status::before { width: 6px; height: 6px; border-radius: 50%; background: currentColor; content: ''; }
        .account-status.is-active { color: #34765b; }
        .account-status.is-inactive { color: #718096; }
        .account-actions { display: flex; align-items: center; gap: 6px; }
        .account-actions .btn { padding: 6px 9px; font-size: 11px; }
        .account-more { position: relative; }
        .account-more summary { display: inline-flex; min-width: 33px; height: 30px; align-items: center; justify-content: center; border: 1px solid #cbd3dd; border-radius: 3px; color: #42536a; cursor: pointer; list-style: none; }
        .account-more summary::-webkit-details-marker { display: none; }
        .account-more summary:hover, .account-more[open] summary { border-color: #315a83; color: #17365d; }
        .account-action-menu { position: absolute; z-index: 20; top: calc(100% + 5px); right: 0; width: 225px; padding: 5px; border: 1px solid #dce2e9; background: #fff; box-shadow: 0 5px 18px #17283d1a; }
        .account-action-menu form { margin: 0; }
        .account-action-menu button, .account-action-menu a { display: block; width: 100%; padding: 9px 10px; border: 0; background: transparent; color: #42536a; text-align: left; text-decoration: none; font-size: 11px; line-height: 1.35; }
        .account-action-menu button:hover, .account-action-menu a:hover { background: #f4f6f9; color: #17365d; }
        .account-action-menu .danger-action { color: #a84444; }
        .account-empty { padding: 40px 18px; text-align: center; color: #718096; }
        .account-empty strong { display: block; margin-bottom: 5px; color: #253247; font-size: 13px; }
        .account-credential-notice { display: flex; justify-content: space-between; gap: 16px; align-items: flex-start; padding: 14px 16px; border: 1px solid #cbdccf; border-left: 3px solid #34765b; background: #f4f8f5; color: #253247; }
        .account-credential-notice code { display: inline-block; margin: 5px 0 2px; padding: 6px 9px; border: 1px solid #dce5de; background: #fff; color: #17365d; font-size: 13px; user-select: all; }
        .account-credential-notice .small { color: #647287; }
        .account-modal .modal-content { border: 1px solid #dce2e9; border-radius: 4px; }
        .account-modal .modal-header, .account-modal .modal-footer { border-color: #e3e8ee; }
        .account-modal .modal-title { color: #17365d; font-size: 16px; font-weight: 650; }
        .account-modal .modal-header p { margin: 4px 0 0; color: #718096; font-size: 11px; }
        .account-form-section { grid-column: 1 / -1; margin: 6px 0 -1px; padding-bottom: 8px; border-bottom: 1px solid #edf0f3; color: #718096; font-size: 10px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
        .account-modal .modal-body { padding: 19px 22px; }
        .account-modal .modal-footer { padding: 13px 22px; }
        @media (max-width: 1279.98px) {
            .account-table-wrap { padding: 0 14px; }
            .account-table { min-width: 0; margin: 0; }
            .account-table thead { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
            .account-table tbody { display: block; }
            .account-table tbody tr { display: block; margin-bottom: 11px; border: 1px solid #e3e8ee; background: #fff; }
            .account-table tbody td { display: grid; grid-template-columns: minmax(100px, 30%) minmax(0, 1fr); gap: 10px; padding: 9px 12px; border-bottom: 1px solid #edf0f3; font-size: 11px; }
            .account-table tbody td::before { content: attr(data-label); color: #718096; font-size: 10px; font-weight: 650; }
            .account-table tbody td.account-cell { display: block; padding: 13px 12px; background: #fafbfd; }
            .account-table tbody td.account-cell::before { content: none; }
            .account-table tbody td.account-action-cell { display: block; border-bottom: 0; }
            .account-table tbody td.account-action-cell::before { display: block; margin-bottom: 8px; }
            .account-actions { justify-content: flex-start; flex-wrap: wrap; }
            .account-table tbody td[colspan] { display: block; border: 0; }
            .account-table tbody td[colspan]::before { content: none; }
        }
        @media (max-width: 575.98px) {
            .account-directory .panel-header { padding: 15px 14px 12px; }
            .account-filters { padding: 0 14px 14px; }
            .account-import-controls { align-items: stretch; flex-direction: column; }
            .account-import-controls .form-control { max-width: none; }
            .account-import-controls .btn { width: 100%; }
            .account-table-wrap { padding: 0 10px; }
            .account-table tbody tr { margin-bottom: 9px; }
            .account-table tbody td { grid-template-columns: 92px minmax(0, 1fr); gap: 8px; padding: 8px 10px; }
            .account-table tbody td.account-cell { padding: 12px 10px; }
            .account-credential-notice { display: block; }
            .account-modal .modal-body { padding: 16px; }
            .account-modal .modal-footer { padding: 12px 16px; }
            .account-modal .modal-footer .btn { flex: 1 1 0; }
        }
    </style>
@endpush

@section('content')
    <div class="account-page">
        @if(session('temporary_password'))
            <div class="account-credential-notice mb-3" role="status">
                <div>
                    <strong>Temporary password — shown once</strong><br>
                    <code>{{ session('temporary_password') }}</code>
                    <div class="small">Share this through an approved private channel. The user must choose a new password at first sign-in.</div>
                </div>
            </div>
        @endif

        <details class="account-import" @if($errors->has('csv')) open @endif>
            <summary>Import accounts from CSV</summary>
            <div class="account-import-body">
                <p>Use the supplied template. CSV import requires an email address and supports up to 500 accounts per upload. Create accounts without email individually.</p>
                <form class="account-import-controls" method="POST" enctype="multipart/form-data" action="{{ route('users.import') }}">
                    @csrf
                    <input class="form-control" type="file" name="csv" accept=".csv,text/csv" aria-label="Choose a CSV file" required>
                    <button class="btn btn-outline-primary text-nowrap" type="submit">Validate and import</button>
                    <a href="{{ route('users.import-template') }}" class="btn btn-outline-secondary text-nowrap">Download template</a>
                </form>
            </div>
        </details>

        <section class="panel account-directory" aria-labelledby="accountDirectoryTitle">
            <div class="panel-header account-directory-heading">
                <div>
                    <h2 id="accountDirectoryTitle">Account directory</h2>
                    <p>Search and manage university system access.</p>
                    <div class="account-count"><span class="account-count-dot" aria-hidden="true"></span><strong>{{ number_format($users->total()) }}</strong> matching {{ $users->total() === 1 ? 'account' : 'accounts' }}</div>
                </div>
            </div>

            <form class="account-filters row g-2" method="GET" action="{{ route('users.index') }}" role="search" aria-label="Filter user accounts">
                <div class="col-12 col-md-5 col-xl-3">
                    <label class="visually-hidden" for="accountSearch">Search accounts</label>
                    <input id="accountSearch" class="form-control" name="search" value="{{ request('search') }}" placeholder="Search name, email, or ID">
                </div>
                <div class="col-6 col-md-3 col-xl-3">
                    <label class="visually-hidden" for="accountDepartment">Department</label>
                    <select id="accountDepartment" name="department_id" class="form-select">
                        <option value="">All departments</option>
                        @foreach($departments as $department)
                            <option value="{{ $department->id }}" @selected(request('department_id') == $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2 col-xl-3">
                    <label class="visually-hidden" for="accountRole">Role</label>
                    <select id="accountRole" name="role_id" class="form-select">
                        <option value="">All roles</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->id }}" @selected(request('role_id') == $role->id)>{{ $role->label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2 col-xl-2 d-grid">
                    <button class="btn btn-outline-primary" type="submit">Apply filters</button>
                </div>
                @if(request()->hasAny(['search', 'department_id', 'role_id']))
                    <div class="col-6 col-md-auto d-grid">
                        <a class="btn btn-outline-secondary" href="{{ route('users.index') }}">Clear filters</a>
                    </div>
                @endif
            </form>

            <div class="account-table-wrap">
                <table class="table account-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Account</th>
                            <th scope="col">Department</th>
                            <th scope="col">Role</th>
                            <th scope="col">Last sign-in</th>
                            <th scope="col">Status</th>
                            <th scope="col"><span class="visually-hidden">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $user)
                            <tr>
                                <td class="account-cell">
                                    <div class="account-person">
                                        <span class="account-monogram" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($user->first_name, 0, 1).\Illuminate\Support\Str::substr($user->last_name, 0, 1)) }}</span>
                                        <div class="min-w-0">
                                            <div class="account-person-name">{{ $user->name }}</div>
                                            <div class="account-person-meta">{{ $user->email ?: 'No email on file' }}<span aria-hidden="true"> · </span>{{ $user->employee_number ?: 'No ID' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="Department">{{ $user->department?->name ?? 'No department' }}</td>
                                <td data-label="Role"><span class="account-role">{{ $user->role->label }}</span></td>
                                <td data-label="Last sign-in"><span class="account-secondary">{{ $user->last_login_at?->format('M j, Y g:i A') ?? 'Never signed in' }}</span></td>
                                <td data-label="Status"><span class="account-status {{ $user->is_active ? 'is-active' : 'is-inactive' }}">{{ $user->is_active ? 'Active' : 'Inactive' }}</span></td>
                                <td class="account-action-cell" data-label="Account actions">
                                    <div class="account-actions">
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('users.edit', $user) }}">Edit</a>
                                        <details class="account-more">
                                            <summary aria-label="More actions for {{ $user->name }}" title="More account actions">
                                                <svg width="16" height="16" viewBox="0 0 20 20" aria-hidden="true"><circle cx="4" cy="10" r="1" fill="currentColor"/><circle cx="10" cy="10" r="1" fill="currentColor"/><circle cx="16" cy="10" r="1" fill="currentColor"/></svg>
                                            </summary>
                                            <div class="account-action-menu">
                                                @if($user->is_active)
                                                    @if($user->email)
                                                        <form method="POST" action="{{ route('users.password-reset', $user) }}">@csrf<button type="submit">Send password setup / reset link</button></form>
                                                    @else
                                                        <form method="POST" action="{{ route('users.temporary-password', $user) }}">@csrf<button type="submit">Generate temporary password</button></form>
                                                    @endif
                                                @endif
                                                <form method="POST" action="{{ route('users.toggle', $user) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button class="{{ $user->is_active ? 'danger-action' : '' }}" type="submit" {{ auth()->id() === $user->id ? 'disabled' : '' }}>
                                                        {{ $user->is_active ? 'Deactivate account' : 'Reactivate account' }}
                                                    </button>
                                                </form>
                                            </div>
                                        </details>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="account-empty"><strong>No user accounts found</strong>Try a different search or clear the selected filters.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($users->hasPages())
                <div class="p-3 border-top">{{ $users->links() }}</div>
            @endif
        </section>
    </div>

    <div class="modal fade account-modal" id="createAccountModal" tabindex="-1" aria-labelledby="createAccountModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
            <div class="modal-content">
                <form method="POST" action="{{ route('users.store') }}">
                    @csrf
                    <div class="modal-header align-items-start">
                        <div>
                            <h2 class="modal-title" id="createAccountModalTitle">Create user account</h2>
                            <p>Email is optional. If blank, sign-in uses the employee/student number.</p>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="account-form-section">User identity</div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="employee_number">Employee / student number</label>
                                <input id="employee_number" name="employee_number" class="form-control" maxlength="40" value="{{ old('employee_number') }}" autocomplete="off">
                                <div class="form-hint mt-1">Required when no email address is provided.</div>
                                @error('employee_number')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="account_email">Email address <span class="text-muted fw-normal">(optional)</span></label>
                                <input id="account_email" name="email" type="email" class="form-control" maxlength="255" value="{{ old('email') }}" autocomplete="email">
                                <div class="form-hint mt-1">Without email, the user signs in with their number.</div>
                                @error('email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label" for="first_name">First name <span class="text-danger">*</span></label>
                                <input id="first_name" name="first_name" class="form-control" maxlength="100" value="{{ old('first_name') }}" autocomplete="given-name" required>
                                @error('first_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label" for="middle_name">Middle name</label>
                                <input id="middle_name" name="middle_name" class="form-control" maxlength="100" value="{{ old('middle_name') }}" autocomplete="additional-name">
                                @error('middle_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label" for="last_name">Last name <span class="text-danger">*</span></label>
                                <input id="last_name" name="last_name" class="form-control" maxlength="100" value="{{ old('last_name') }}" autocomplete="family-name" required>
                                @error('last_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>

                            <div class="account-form-section mt-2">Contact and access</div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="contact_number">Contact number</label>
                                <input id="contact_number" name="contact_number" type="tel" class="form-control" maxlength="40" value="{{ old('contact_number') }}" autocomplete="tel">
                                @error('contact_number')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="position">Position</label>
                                <input id="position" name="position" class="form-control" maxlength="150" value="{{ old('position') }}" autocomplete="organization-title">
                                @error('position')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="department_id">Department</label>
                                <select id="department_id" name="department_id" class="form-select">
                                    <option value="">No department</option>
                                    @foreach($departments->where('is_active', true) as $department)
                                        <option value="{{ $department->id }}" @selected(old('department_id') == $department->id)>{{ $department->name }}</option>
                                    @endforeach
                                </select>
                                @error('department_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="role_id">System role <span class="text-danger">*</span></label>
                                <select id="role_id" name="role_id" class="form-select" required>
                                    @foreach($roles as $role)
                                        <option value="{{ $role->id }}" @selected(old('role_id') == $role->id)>{{ $role->label }}</option>
                                    @endforeach
                                </select>
                                @error('role_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer justify-content-between flex-wrap gap-2">
                        <div class="form-hint me-auto">A temporary password or setup link will be provided after creation.</div>
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create account</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const accountEmail = document.getElementById('account_email');
        const employeeNumber = document.getElementById('employee_number');
        function updateIdentifierRequirement() {
            if (accountEmail && employeeNumber) employeeNumber.required = accountEmail.value.trim() === '';
        }
        accountEmail?.addEventListener('input', updateIdentifierRequirement);
        updateIdentifierRequirement();

        document.querySelectorAll('.account-more').forEach((menu) => {
            menu.addEventListener('toggle', () => {
                if (!menu.open) return;
                document.querySelectorAll('.account-more[open]').forEach((otherMenu) => {
                    if (otherMenu !== menu) otherMenu.open = false;
                });
            });
        });

        const accountFormHasErrors = @json($errors->has('employee_number') || $errors->has('email') || $errors->has('first_name') || $errors->has('middle_name') || $errors->has('last_name') || $errors->has('contact_number') || $errors->has('position') || $errors->has('department_id') || $errors->has('role_id'));
        if (accountFormHasErrors) {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('createAccountModal')).show();
        }
    </script>
@endpush
