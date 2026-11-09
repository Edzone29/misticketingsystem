@extends('layouts.app')

@section('title', 'Departments')
@section('heading', 'Departments')

@section('actions')
    <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#createDepartmentModal">
        <svg class="me-1" width="15" height="15" viewBox="0 0 20 20" aria-hidden="true"><path d="M10 4v12M4 10h12" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        Add department
    </button>
@endsection

@push('head')
    <style>
        .department-page { --department-line: #e3e8ee; }
        .department-directory .panel-header { align-items: flex-start; }
        .department-result-count { display: inline-flex; align-items: center; gap: 7px; margin-top: 7px; color: #718096; font-size: 11px; }
        .department-result-count strong { color: #253247; font-weight: 650; }
        .department-result-dot { width: 6px; height: 6px; border-radius: 50%; background: #c5a24a; }
        .department-filters { padding: 0 20px 17px; }
        .department-filters .form-control { min-height: 39px; }
        .department-table-wrap { overflow: visible; }
        .department-table { min-width: 780px; }
        .department-table th:last-child, .department-table td:last-child { width: 150px; }
        .department-main-cell { min-width: 250px; }
        .department-code { display: inline-block; margin-bottom: 4px; padding: 3px 6px; border: 1px solid #e1e7ee; background: #f7f9fb; color: #315a83; font-size: 10px; font-weight: 650; }
        .department-name { color: #253247; font-size: 12px; font-weight: 650; line-height: 1.4; overflow-wrap: anywhere; }
        .department-meta { color: #718096; font-size: 10px; line-height: 1.45; overflow-wrap: anywhere; }
        .department-count { display: inline-flex; min-width: 28px; min-height: 24px; align-items: center; justify-content: center; padding: 3px 7px; border: 1px solid #e1e7ee; background: #f7f9fb; color: #42536a; font-size: 11px; font-weight: 650; }
        .department-status { display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; font-size: 10px; font-weight: 650; }
        .department-status::before { width: 6px; height: 6px; border-radius: 50%; background: currentColor; content: ''; }
        .department-status.is-active { color: #34765b; }
        .department-status.is-inactive { color: #718096; }
        .department-actions { display: flex; align-items: center; gap: 6px; }
        .department-actions .btn { padding: 6px 9px; font-size: 11px; }
        .department-more { position: relative; }
        .department-more summary { display: inline-flex; min-width: 33px; height: 30px; align-items: center; justify-content: center; border: 1px solid #cbd3dd; border-radius: 3px; color: #42536a; cursor: pointer; list-style: none; }
        .department-more summary::-webkit-details-marker { display: none; }
        .department-more summary:hover, .department-more[open] summary { border-color: #315a83; color: #17365d; }
        .department-action-menu { position: absolute; z-index: 20; top: calc(100% + 5px); right: 0; width: 205px; padding: 5px; border: 1px solid #dce2e9; background: #fff; box-shadow: 0 5px 18px #17283d1a; }
        .department-action-menu form { margin: 0; }
        .department-action-menu button { display: block; width: 100%; padding: 9px 10px; border: 0; background: transparent; color: #a84444; text-align: left; font-size: 11px; line-height: 1.35; }
        .department-action-menu button:hover { background: #f4f6f9; }
        .department-empty { padding: 40px 18px; text-align: center; color: #718096; }
        .department-empty strong { display: block; margin-bottom: 5px; color: #253247; font-size: 13px; }
        .department-note { padding: 14px 20px; border-top: 1px solid var(--department-line); background: #fafbfd; color: #718096; font-size: 11px; line-height: 1.5; }
        .department-modal .modal-content { border: 1px solid #dce2e9; border-radius: 4px; }
        .department-modal .modal-header, .department-modal .modal-footer { border-color: #e3e8ee; }
        .department-modal .modal-title { color: #17365d; font-size: 16px; font-weight: 650; }
        .department-modal .modal-header p { margin: 4px 0 0; color: #718096; font-size: 11px; }
        .department-modal .modal-body { padding: 19px 22px; }
        .department-modal .modal-footer { padding: 13px 22px; }
        .department-form-section { grid-column: 1 / -1; margin: 6px 0 -1px; padding-bottom: 8px; border-bottom: 1px solid #edf0f3; color: #718096; font-size: 10px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
        @media (max-width: 1279.98px) {
            .department-table-wrap { padding: 0 14px; }
            .department-table { min-width: 0; margin: 0; }
            .department-table thead { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
            .department-table tbody { display: block; }
            .department-table tbody tr { display: block; margin-bottom: 11px; border: 1px solid #e3e8ee; background: #fff; }
            .department-table tbody td { display: grid; grid-template-columns: minmax(100px, 30%) minmax(0, 1fr); gap: 10px; padding: 9px 12px; border-bottom: 1px solid #edf0f3; font-size: 11px; }
            .department-table tbody td::before { content: attr(data-label); color: #718096; font-size: 10px; font-weight: 650; }
            .department-table tbody td.department-main-cell { display: block; padding: 13px 12px; background: #fafbfd; }
            .department-table tbody td.department-main-cell::before { content: none; }
            .department-table tbody td.department-action-cell { display: block; border-bottom: 0; }
            .department-table tbody td.department-action-cell::before { display: block; margin-bottom: 8px; }
            .department-actions { justify-content: flex-start; flex-wrap: wrap; }
            .department-table tbody td[colspan] { display: block; border: 0; }
            .department-table tbody td[colspan]::before { content: none; }
        }
        @media (max-width: 575.98px) {
            .department-directory .panel-header { padding: 15px 14px 12px; }
            .department-filters { padding: 0 14px 14px; }
            .department-table-wrap { padding: 0 10px; }
            .department-table tbody tr { margin-bottom: 9px; }
            .department-table tbody td { grid-template-columns: 92px minmax(0, 1fr); gap: 8px; padding: 8px 10px; }
            .department-table tbody td.department-main-cell { padding: 12px 10px; }
            .department-note { padding: 13px 14px; }
            .department-modal .modal-body { padding: 16px; }
            .department-modal .modal-footer { padding: 12px 16px; }
            .department-modal .modal-footer .btn { flex: 1 1 0; }
        }
    </style>
@endpush

@section('content')
    @php
        $reopenDepartment = $departments->getCollection()->firstWhere('id', (int) old('editing_department_id'));
        $departmentFields = ['code', 'name', 'description', 'head_user_id', 'is_active'];
        $createDepartmentHasErrors = ! $reopenDepartment && collect($departmentFields)->contains(fn ($field) => $errors->has($field));
    @endphp

    <div class="department-page">
        <section class="panel department-directory" aria-labelledby="departmentDirectoryTitle">
            <div class="panel-header">
                <div>
                    <h2 id="departmentDirectoryTitle">Department directory</h2>
                    <p>Manage organizational units, department heads, and active personnel.</p>
                    <div class="department-result-count"><span class="department-result-dot" aria-hidden="true"></span><strong>{{ number_format($departments->total()) }}</strong> matching {{ $departments->total() === 1 ? 'department' : 'departments' }}</div>
                </div>
            </div>

            <form class="department-filters row g-2" method="GET" action="{{ route('departments.index') }}" role="search" aria-label="Search departments">
                <div class="col-12 col-md-8 col-lg-9">
                    <label class="visually-hidden" for="department-search">Search departments</label>
                    <input class="form-control" id="department-search" name="search" value="{{ request('search') }}" maxlength="100" placeholder="Search department name or code">
                </div>
                <div class="col-6 col-md-2 col-lg-2 d-grid"><button class="btn btn-outline-primary" type="submit">Apply filters</button></div>
                @if(request()->has('search'))
                    <div class="col-6 col-md-auto d-grid"><a class="btn btn-outline-secondary" href="{{ route('departments.index') }}">Clear filters</a></div>
                @endif
            </form>

            <div class="department-table-wrap">
                <table class="table department-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Department</th>
                            <th scope="col">Department head</th>
                            <th scope="col">Active personnel</th>
                            <th scope="col">Status</th>
                            <th scope="col"><span class="visually-hidden">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($departments as $department)
                            <tr>
                                <td class="department-main-cell">
                                    <span class="department-code">{{ $department->code }}</span>
                                    <div class="department-name">{{ $department->name }}</div>
                                    @if($department->description)<div class="department-meta mt-1">{{ $department->description }}</div>@endif
                                </td>
                                <td data-label="Department head">{{ $department->head?->name ?? 'Not assigned' }}</td>
                                <td data-label="Active personnel"><span class="department-count">{{ number_format($department->active_users_count) }}</span></td>
                                <td data-label="Status"><span class="department-status {{ $department->is_active ? 'is-active' : 'is-inactive' }}">{{ $department->is_active ? 'Active' : 'Inactive' }}</span></td>
                                <td class="department-action-cell" data-label="Department actions">
                                    <div class="department-actions">
                                        <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#editDepartmentModal"
                                            data-update-url="{{ route('departments.update', $department) }}"
                                            data-department-id="{{ $department->id }}"
                                            data-code="{{ $department->code }}"
                                            data-name="{{ $department->name }}"
                                            data-description="{{ $department->description }}"
                                            data-head-user-id="{{ $department->head_user_id }}"
                                            data-is-active="{{ $department->is_active ? '1' : '0' }}"
                                            data-head-candidates="{{ $department->users->map(fn ($person) => ['id' => $person->id, 'name' => $person->name])->values()->toJson() }}"
                                            aria-label="Edit {{ $department->name }}">Edit</button>
                                        @if($department->is_active)
                                            <details class="department-more">
                                                <summary aria-label="More actions for {{ $department->name }}" title="More department actions">
                                                    <svg width="16" height="16" viewBox="0 0 20 20" aria-hidden="true"><circle cx="4" cy="10" r="1" fill="currentColor"/><circle cx="10" cy="10" r="1" fill="currentColor"/><circle cx="16" cy="10" r="1" fill="currentColor"/></svg>
                                                </summary>
                                                <div class="department-action-menu">
                                                    <form method="POST" action="{{ route('departments.destroy', $department) }}" onsubmit="return confirm('Deactivate this department? Existing ticket and asset history will be retained.')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit">Deactivate department</button>
                                                    </form>
                                                </div>
                                            </details>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5"><div class="department-empty"><strong>No departments found</strong>Try another search or add a department to the directory.</div></td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($departments->hasPages())
                <div class="p-3 border-top">{{ $departments->links() }}</div>
            @endif
            <div class="department-note">Departments with linked users, assets, or tickets are deactivated rather than permanently deleted, preserving their records.</div>
        </section>
    </div>

    <div class="modal fade department-modal" id="createDepartmentModal" tabindex="-1" aria-labelledby="createDepartmentModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
            <div class="modal-content">
                <form method="POST" action="{{ route('departments.store') }}">
                    @csrf
                    <div class="modal-header align-items-start">
                        <div><h2 class="modal-title" id="createDepartmentModalTitle">Add department</h2><p>Department codes must be unique across the university.</p></div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="department-form-section">Department information</div>
                            <div class="col-12 col-md-4">
                                <label class="form-label" for="create-department-code">Department code <span class="text-danger">*</span></label>
                                <input class="form-control" id="create-department-code" name="code" maxlength="20" required autocomplete="off" value="{{ $createDepartmentHasErrors ? old('code') : '' }}">
                                @if($createDepartmentHasErrors)@error('code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                            </div>
                            <div class="col-12 col-md-8">
                                <label class="form-label" for="create-department-name">Department name <span class="text-danger">*</span></label>
                                <input class="form-control" id="create-department-name" name="name" maxlength="255" required value="{{ $createDepartmentHasErrors ? old('name') : '' }}">
                                @if($createDepartmentHasErrors)@error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="create-department-description">Description</label>
                                <textarea class="form-control" id="create-department-description" name="description" rows="4" maxlength="2000">{{ $createDepartmentHasErrors ? old('description') : '' }}</textarea>
                                @if($createDepartmentHasErrors)@error('description')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer justify-content-end flex-wrap gap-2">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-primary" type="submit">Add department</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade department-modal" id="editDepartmentModal" tabindex="-1" aria-labelledby="editDepartmentModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
            <div class="modal-content">
                <form id="editDepartmentForm" method="POST" action="{{ $reopenDepartment ? route('departments.update', $reopenDepartment) : route('departments.index') }}">
                    @csrf
                    @method('PUT')
                    <input id="editingDepartmentId" type="hidden" name="editing_department_id" value="{{ $reopenDepartment?->id }}">
                    <div class="modal-header align-items-start">
                        <div><h2 class="modal-title" id="editDepartmentModalTitle">Edit department</h2><p>Update department identity, head, and active status.</p></div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        @if($reopenDepartment && $errors->any())
                            <div class="alert alert-danger" role="alert">Please review the department details and try again.</div>
                        @endif
                        <div class="row g-3">
                            <div class="department-form-section">Department information</div>
                            <div class="col-12 col-md-4">
                                <label class="form-label" for="edit-department-code">Department code <span class="text-danger">*</span></label>
                                <input class="form-control" id="edit-department-code" name="code" maxlength="20" required value="{{ $reopenDepartment ? old('code', $reopenDepartment->code) : '' }}">
                                @if($reopenDepartment)@error('code')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                            </div>
                            <div class="col-12 col-md-8">
                                <label class="form-label" for="edit-department-name">Department name <span class="text-danger">*</span></label>
                                <input class="form-control" id="edit-department-name" name="name" maxlength="255" required value="{{ $reopenDepartment ? old('name', $reopenDepartment->name) : '' }}">
                                @if($reopenDepartment)@error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="edit-department-description">Description</label>
                                <textarea class="form-control" id="edit-department-description" name="description" rows="3" maxlength="2000">{{ $reopenDepartment ? old('description', $reopenDepartment->description) : '' }}</textarea>
                                @if($reopenDepartment)@error('description')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                            </div>
                            <div class="department-form-section mt-2">Leadership and status</div>
                            <div class="col-12 col-md-7">
                                <label class="form-label" for="edit-department-head">Department head</label>
                                <select class="form-select" id="edit-department-head" name="head_user_id">
                                    <option value="" @selected($reopenDepartment && old('head_user_id', $reopenDepartment->head_user_id) == '')>No department head assigned</option>
                                    @if($reopenDepartment && $reopenDepartment->head_user_id && ! $reopenDepartment->users->contains('id', $reopenDepartment->head_user_id))
                                        <option value="" disabled>Previous head is inactive; select an active member</option>
                                    @endif
                                    @foreach(($reopenDepartment?->users ?? collect()) as $person)
                                        <option value="{{ $person->id }}" @selected($reopenDepartment && old('head_user_id', $reopenDepartment->head_user_id) == $person->id)>{{ $person->name }}</option>
                                    @endforeach
                                </select>
                                <div class="form-hint mt-1">Choose an active user assigned to this department, or leave the role unassigned.</div>
                                @if($reopenDepartment)@error('head_user_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                            </div>
                            <div class="col-12 col-md-5">
                                <label class="form-label" for="edit-department-status">Department status <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit-department-status" name="is_active" required>
                                    <option value="1" @selected($reopenDepartment && old('is_active', $reopenDepartment->is_active ? '1' : '0') == '1')>Active</option>
                                    <option value="0" @selected($reopenDepartment && old('is_active', $reopenDepartment->is_active ? '1' : '0') == '0')>Inactive</option>
                                </select>
                                @if($reopenDepartment)@error('is_active')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer justify-content-end flex-wrap gap-2">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-primary" type="submit">Save changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const editDepartmentModalElement = document.getElementById('editDepartmentModal');
        const editDepartmentForm = document.getElementById('editDepartmentForm');
        const editDepartmentHead = document.getElementById('edit-department-head');

        editDepartmentModalElement?.addEventListener('show.bs.modal', (event) => {
            const trigger = event.relatedTarget;
            if (!trigger) return;

            editDepartmentForm.action = trigger.dataset.updateUrl;
            document.getElementById('editingDepartmentId').value = trigger.dataset.departmentId ?? '';
            document.getElementById('edit-department-code').value = trigger.dataset.code ?? '';
            document.getElementById('edit-department-name').value = trigger.dataset.name ?? '';
            document.getElementById('edit-department-description').value = trigger.dataset.description ?? '';
            document.getElementById('edit-department-status').value = trigger.dataset.isActive ?? '1';

            const headCandidates = JSON.parse(trigger.dataset.headCandidates || '[]');
            editDepartmentHead.replaceChildren(new Option('No department head assigned', ''));
            headCandidates.forEach((person) => {
                const option = new Option(person.name, person.id);
                option.selected = String(person.id) === String(trigger.dataset.headUserId ?? '');
                editDepartmentHead.add(option);
            });
        });

        document.querySelectorAll('.department-more').forEach((menu) => {
            menu.addEventListener('toggle', () => {
                if (!menu.open) return;
                document.querySelectorAll('.department-more[open]').forEach((otherMenu) => {
                    if (otherMenu !== menu) otherMenu.open = false;
                });
            });
        });

        const reopenEditDepartmentModal = @json((bool) $reopenDepartment);
        const reopenCreateDepartmentModal = @json((bool) $createDepartmentHasErrors);
        if (reopenEditDepartmentModal) {
            bootstrap.Modal.getOrCreateInstance(editDepartmentModalElement).show();
        } else if (reopenCreateDepartmentModal) {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('createDepartmentModal')).show();
        }
    </script>
@endpush
