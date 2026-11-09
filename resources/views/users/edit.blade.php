@extends('layouts.app')

@section('title', 'Edit user account')
@section('heading', 'Edit user account')

@section('actions')
    <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">Back to accounts</a>
@endsection

@push('head')
    <style>
        .edit-account-wrap { max-width: 1000px; margin: 0 auto; }
        .edit-account-top { display: flex; align-items: center; gap: 13px; }
        .edit-account-avatar { display: inline-flex; width: 44px; height: 44px; flex: 0 0 44px; align-items: center; justify-content: center; border: 1px solid #dce4ec; border-radius: 50%; background: #f1f5f9; color: #315a83; font-size: 13px; font-weight: 700; }
        .edit-account-subtitle { margin-top: 4px; color: #718096; font-size: 11px; }
        .edit-account-status { margin-left: auto; }
        .edit-account-form { padding: 0 22px 21px; }
        .edit-section-title { grid-column: 1 / -1; margin: 9px 0 -1px; padding: 0 0 8px; border-bottom: 1px solid #edf0f3; color: #718096; font-size: 10px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
        .edit-account-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 7px; padding-top: 16px; border-top: 1px solid #edf0f3; }
        .edit-account-actions .btn { min-width: 112px; }
        @media(max-width: 575.98px) {
            .edit-account-top { align-items: flex-start; }
            .edit-account-avatar { width: 38px; height: 38px; flex-basis: 38px; }
            .edit-account-status { margin-left: 0; }
            .edit-account-form { padding: 0 15px 16px; }
            .edit-account-actions { display: grid; grid-template-columns: 1fr 1fr; }
            .edit-account-actions .btn { width: 100%; min-width: 0; }
        }
    </style>
@endpush

@push('scripts')
    <script>
        const accountEmail = document.getElementById('email');
        const employeeNumber = document.getElementById('employee_number');
        function updateIdentifierRequirement() {
            if (accountEmail && employeeNumber) employeeNumber.required = accountEmail.value.trim() === '';
        }
        accountEmail?.addEventListener('input', updateIdentifierRequirement);
        updateIdentifierRequirement();
    </script>
@endpush

@section('content')
    <div class="edit-account-wrap">
        <section class="panel" aria-labelledby="editAccountTitle">
            <div class="panel-header">
                <div class="edit-account-top">
                    <span class="edit-account-avatar" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($user->first_name, 0, 1).\Illuminate\Support\Str::substr($user->last_name, 0, 1)) }}</span>
                    <div>
                        <h2 id="editAccountTitle">{{ $user->name }}</h2>
                        <div class="edit-account-subtitle">Update contact details, department, and system access.</div>
                    </div>
                </div>
                <span class="status-badge {{ $user->is_active ? 'status-resolved' : 'status-closed' }} edit-account-status">{{ $user->is_active ? 'Active account' : 'Inactive account' }}</span>
            </div>

            <form method="POST" action="{{ route('users.update', $user) }}" class="edit-account-form">
                @csrf
                @method('PUT')
                <div class="row g-3">
                    <div class="edit-section-title">Sign-in details</div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="employee_number">Employee / student number <span class="text-muted fw-normal">(required if no email)</span></label>
                        <input id="employee_number" name="employee_number" class="form-control" maxlength="40" value="{{ old('employee_number', $user->employee_number) }}" autocomplete="off">
                        @error('employee_number')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="email">Email address <span class="text-muted fw-normal">(optional)</span></label>
                        <input id="email" name="email" type="email" class="form-control" maxlength="255" value="{{ old('email', $user->email) }}" autocomplete="email">
                        <div class="form-hint mt-1">If left blank, the user signs in with their employee/student number.</div>
                        @error('email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <div class="edit-section-title mt-3">Personal information</div>
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="first_name">First name <span class="text-danger">*</span></label>
                        <input id="first_name" name="first_name" class="form-control" maxlength="100" value="{{ old('first_name', $user->first_name) }}" autocomplete="given-name" required>
                        @error('first_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="middle_name">Middle name</label>
                        <input id="middle_name" name="middle_name" class="form-control" maxlength="100" value="{{ old('middle_name', $user->middle_name) }}" autocomplete="additional-name">
                        @error('middle_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="last_name">Last name <span class="text-danger">*</span></label>
                        <input id="last_name" name="last_name" class="form-control" maxlength="100" value="{{ old('last_name', $user->last_name) }}" autocomplete="family-name" required>
                        @error('last_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="contact_number">Contact number</label>
                        <input id="contact_number" name="contact_number" type="tel" class="form-control" maxlength="40" value="{{ old('contact_number', $user->contact_number) }}" autocomplete="tel">
                        @error('contact_number')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="position">Position</label>
                        <input id="position" name="position" class="form-control" maxlength="150" value="{{ old('position', $user->position) }}" autocomplete="organization-title">
                        @error('position')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>

                    <div class="edit-section-title mt-3">Department and permissions</div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="department_id">Department</label>
                        <select id="department_id" name="department_id" class="form-select">
                            <option value="">No department</option>
                            @foreach($departments as $department)
                                <option value="{{ $department->id }}" @selected(old('department_id', $user->department_id) == $department->id)>
                                    {{ $department->name }}{{ $department->is_active ? '' : ' (Inactive)' }}
                                </option>
                            @endforeach
                        </select>
                        @error('department_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="role_id">System role <span class="text-danger">*</span></label>
                        <select id="role_id" name="role_id" class="form-select" required>
                            @foreach($roles as $role)
                                <option value="{{ $role->id }}" @selected(old('role_id', $user->role_id) == $role->id)>{{ $role->label }}</option>
                            @endforeach
                        </select>
                        @error('role_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 edit-account-actions">
                        <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">Cancel</a>
                        <button class="btn btn-primary" type="submit">Save changes</button>
                    </div>
                </div>
            </form>
        </section>
    </div>
@endsection
