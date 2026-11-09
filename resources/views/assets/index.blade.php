@extends('layouts.app')

@section('title', 'IT assets')
@section('heading', 'IT asset inventory')

@section('actions')
    <div class="asset-page-actions">
        <a class="btn btn-outline-secondary" href="{{ route('asset-categories.index') }}">Manage categories</a>
        <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#createAssetModal">
            <svg class="me-1" width="15" height="15" viewBox="0 0 20 20" aria-hidden="true"><path d="M10 4v12M4 10h12" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            Register asset
        </button>
    </div>
@endsection

@push('head')
    <style>
        .asset-page { --asset-line: #e3e8ee; }
        .asset-page-actions { display: flex; flex-wrap: wrap; gap: 8px; }
        .asset-inventory-panel .panel-header { align-items: flex-start; }
        .asset-result-count { display: inline-flex; align-items: center; gap: 7px; margin-top: 7px; color: #718096; font-size: 11px; }
        .asset-result-count strong { color: #253247; font-weight: 650; }
        .asset-result-dot { width: 6px; height: 6px; border-radius: 50%; background: #c5a24a; }
        .asset-filters { padding: 0 20px 17px; }
        .asset-filters .form-control, .asset-filters .form-select { min-height: 39px; }
        .asset-table-wrap { overflow: visible; }
        .asset-table { min-width: 970px; }
        .asset-table th:last-child, .asset-table td:last-child { width: 145px; }
        .asset-main-cell { min-width: 220px; }
        .asset-name { color: #253247; font-size: 12px; font-weight: 650; line-height: 1.4; }
        .asset-main-meta { margin-top: 3px; color: #718096; font-size: 10px; line-height: 1.45; overflow-wrap: anywhere; }
        .asset-reference { color: #315a83; font-size: 11px; font-weight: 650; text-decoration: none; }
        .asset-reference:hover { color: #17365d; text-decoration: underline; }
        .asset-status { display: inline-block; max-width: 150px; padding: 4px 7px; border-radius: 3px; font-size: 10px; font-weight: 650; line-height: 1.35; }
        .asset-status.is-service { background: #eaf4ee; color: #34765b; }
        .asset-status.is-repair { background: #fff3df; color: #906318; }
        .asset-status.is-storage { background: #e9f0f8; color: #315a83; }
        .asset-status.is-retired { background: #edf1f5; color: #65758a; }
        .asset-condition { color: #42536a; font-size: 11px; }
        .asset-warranty { color: #647287; font-size: 11px; white-space: nowrap; }
        .asset-actions { display: flex; align-items: center; gap: 6px; }
        .asset-actions .btn { padding: 6px 9px; font-size: 11px; }
        .asset-summary { padding: 18px 20px; border-top: 1px solid var(--asset-line); background: #fafbfd; color: #718096; font-size: 11px; line-height: 1.5; }
        .asset-modal .modal-content { border: 1px solid #dce2e9; border-radius: 4px; }
        .asset-modal .modal-header, .asset-modal .modal-footer { border-color: #e3e8ee; }
        .asset-modal .modal-title { color: #17365d; font-size: 16px; font-weight: 650; }
        .asset-modal .modal-header p { margin: 4px 0 0; color: #718096; font-size: 11px; }
        .asset-modal .modal-body { padding: 19px 22px; }
        .asset-modal .modal-footer { padding: 13px 22px; }
        .asset-form-section { grid-column: 1 / -1; margin: 6px 0 -1px; padding-bottom: 8px; border-bottom: 1px solid #edf0f3; color: #718096; font-size: 10px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
        .asset-card-empty { padding: 38px 16px; text-align: center; color: #718096; }
        .asset-card-empty strong { display: block; margin-bottom: 5px; color: #253247; font-size: 13px; }
        @media (max-width: 1279.98px) {
            .asset-table-wrap { padding: 0 14px; }
            .asset-table { min-width: 0; margin: 0; }
            .asset-table thead { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
            .asset-table tbody { display: block; }
            .asset-table tbody tr { display: block; margin-bottom: 11px; border: 1px solid #e3e8ee; background: #fff; }
            .asset-table tbody td { display: grid; grid-template-columns: minmax(105px, 30%) minmax(0, 1fr); gap: 10px; padding: 9px 12px; border-bottom: 1px solid #edf0f3; font-size: 11px; }
            .asset-table tbody td::before { content: attr(data-label); color: #718096; font-size: 10px; font-weight: 650; }
            .asset-table tbody td.asset-main-cell { display: block; padding: 13px 12px; background: #fafbfd; }
            .asset-table tbody td.asset-main-cell::before { content: none; }
            .asset-table tbody td.asset-action-cell { display: block; border-bottom: 0; }
            .asset-table tbody td.asset-action-cell::before { display: block; margin-bottom: 8px; }
            .asset-table tbody td[colspan] { display: block; border: 0; }
            .asset-table tbody td[colspan]::before { content: none; }
        }
        @media (max-width: 575.98px) {
            .asset-page-actions { width: 100%; }
            .asset-page-actions .btn { flex: 1 1 auto; }
            .asset-inventory-panel .panel-header { padding: 15px 14px 12px; }
            .asset-filters { padding: 0 14px 14px; }
            .asset-filter-actions .btn { flex: 1 1 0; }
            .asset-table-wrap { padding: 0 10px; }
            .asset-table tbody tr { margin-bottom: 9px; }
            .asset-table tbody td { grid-template-columns: 86px minmax(0, 1fr); gap: 8px; padding: 8px 10px; }
            .asset-table tbody td.asset-main-cell { padding: 12px 10px; }
            .asset-modal .modal-body { padding: 16px; }
            .asset-modal .modal-footer { padding: 12px 16px; }
            .asset-modal .modal-footer .btn { flex: 1 1 0; }
            .asset-summary { padding: 14px; }
        }
    </style>
@endpush

@section('content')
    @php
        $reopenAsset = $assets->getCollection()->firstWhere('id', (int) old('editing_asset_id'));
        $assetErrorFields = ['asset_tag', 'serial_number', 'name', 'asset_category_id', 'brand', 'model', 'department_id', 'assigned_user_id', 'building', 'room_number', 'acquired_on', 'warranty_expires_on', 'condition', 'status', 'remarks'];
        $createAssetHasErrors = ! $reopenAsset && collect($assetErrorFields)->contains(fn ($field) => $errors->has($field));
    @endphp

    <div class="asset-page">
        <section class="panel asset-inventory-panel" aria-labelledby="assetDirectoryTitle">
            <div class="panel-header">
                <div>
                    <h2 id="assetDirectoryTitle">Inventory directory</h2>
                    <p>Search, filter, review, and update university IT equipment.</p>
                    <div class="asset-result-count"><span class="asset-result-dot" aria-hidden="true"></span><strong>{{ number_format($assets->total()) }}</strong> matching {{ $assets->total() === 1 ? 'asset' : 'assets' }}</div>
                </div>
            </div>

            <form method="GET" action="{{ route('assets.index') }}" class="asset-filters row g-2" role="search" aria-label="Filter the asset inventory">
                <div class="col-12 col-lg-3">
                    <label class="visually-hidden" for="asset-search">Search assets</label>
                    <input id="asset-search" name="search" value="{{ request('search') }}" class="form-control" maxlength="100" placeholder="Search asset tag, name, or serial number">
                </div>
                <div class="col-6 col-lg-2">
                    <label class="visually-hidden" for="asset-filter-status">Filter by status</label>
                    <select id="asset-filter-status" name="status" class="form-select">
                        <option value="">All statuses</option>
                        @foreach(['In Service', 'Under Repair', 'In Storage', 'Retired'] as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <label class="visually-hidden" for="asset-filter-category">Filter by category</label>
                    <select id="asset-filter-category" name="asset_category_id" class="form-select">
                        <option value="">All categories</option>
                        @foreach($filterCategories as $category)
                            <option value="{{ $category->id }}" @selected((string) request('asset_category_id') === (string) $category->id)>{{ $category->name }}{{ $category->is_active ? '' : ' (Inactive)' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <label class="visually-hidden" for="asset-filter-department">Filter by department</label>
                    <select id="asset-filter-department" name="department_id" class="form-select">
                        <option value="">All departments</option>
                        @foreach($filterDepartments as $department)
                            <option value="{{ $department->id }}" @selected((string) request('department_id') === (string) $department->id)>{{ $department->name }}{{ $department->is_active ? '' : ' (Inactive)' }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-lg-3 d-flex gap-2 asset-filter-actions">
                    <button class="btn btn-outline-primary flex-grow-1" type="submit">Apply filters</button>
                    @if(request()->hasAny(['search', 'status', 'asset_category_id', 'department_id']))
                        <a class="btn btn-outline-secondary" href="{{ route('assets.index') }}" aria-label="Clear asset filters" title="Clear filters">Clear filters</a>
                    @endif
                </div>
            </form>

            <div class="asset-table-wrap">
                <table class="table asset-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Asset</th>
                            <th scope="col">Category</th>
                            <th scope="col">Department / user</th>
                            <th scope="col">Condition</th>
                            <th scope="col">Status</th>
                            <th scope="col">Warranty</th>
                            <th scope="col"><span class="visually-hidden">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($assets as $asset)
                            <tr>
                                <td class="asset-main-cell">
                                    <a class="asset-reference" href="{{ route('assets.show', $asset) }}">{{ $asset->asset_tag }}</a>
                                    <div class="asset-name">{{ $asset->name }}</div>
                                    <div class="asset-main-meta">Serial: {{ $asset->serial_number ?: 'Not recorded' }}</div>
                                </td>
                                <td data-label="Category">{{ $asset->category?->name ?? 'Unavailable category' }}</td>
                                <td data-label="Department / assigned user">
                                    <span>{{ $asset->department?->name ?? 'Unassigned' }}</span>
                                    <div class="asset-main-meta">{{ $asset->assignedUser?->name ?? 'No assigned user' }}</div>
                                </td>
                                <td data-label="Condition"><span class="asset-condition">{{ $asset->condition }}</span></td>
                                <td data-label="Status">
                                    <span class="asset-status {{ match($asset->status) { 'In Service' => 'is-service', 'Under Repair' => 'is-repair', 'In Storage' => 'is-storage', default => 'is-retired' } }}">{{ $asset->status }}</span>
                                </td>
                                <td data-label="Warranty"><span class="asset-warranty">{{ $asset->warranty_expires_on?->format('M j, Y') ?? 'Not recorded' }}</span></td>
                                <td class="asset-action-cell" data-label="Asset actions">
                                    <div class="asset-actions">
                                        <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#editAssetModal"
                                            data-update-url="{{ route('assets.update', $asset) }}"
                                            data-asset-id="{{ $asset->id }}"
                                            data-asset-tag="{{ $asset->asset_tag }}"
                                            data-serial-number="{{ $asset->serial_number }}"
                                            data-name="{{ $asset->name }}"
                                            data-category-id="{{ $asset->asset_category_id }}"
                                            data-brand="{{ $asset->brand }}"
                                            data-model="{{ $asset->model }}"
                                            data-department-id="{{ $asset->department_id }}"
                                            data-assigned-user-id="{{ $asset->assigned_user_id }}"
                                            data-building="{{ $asset->building }}"
                                            data-room-number="{{ $asset->room_number }}"
                                            data-acquired-on="{{ $asset->acquired_on?->format('Y-m-d') }}"
                                            data-warranty-expires-on="{{ $asset->warranty_expires_on?->format('Y-m-d') }}"
                                            data-condition="{{ $asset->condition }}"
                                            data-status="{{ $asset->status }}"
                                            data-remarks="{{ $asset->remarks }}"
                                            aria-label="Edit {{ $asset->asset_tag }}">Edit</button>
                                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('assets.show', $asset) }}" aria-label="View {{ $asset->asset_tag }}">View</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <div class="asset-card-empty"><strong>No assets match these filters</strong>Adjust or clear the filters, or register an asset to begin the inventory.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($assets->hasPages())
                <div class="p-3 border-top">{{ $assets->links() }}</div>
            @endif
            <div class="asset-summary">Asset records are retained for service and maintenance history. Mark equipment as retired when it is no longer in use.</div>
        </section>
    </div>

    <div class="modal fade asset-modal" id="createAssetModal" tabindex="-1" aria-labelledby="createAssetModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable modal-fullscreen-lg-down">
            <div class="modal-content">
                <form method="POST" action="{{ route('assets.store') }}">
                    @csrf
                    <div class="modal-header align-items-start">
                        <div><h2 class="modal-title" id="createAssetModalTitle">Register an asset</h2><p>Add a university equipment record to the inventory.</p></div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="asset-form-section">Asset identification</div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="create-asset-tag">Asset tag <span class="text-danger">*</span></label>
                                <input id="create-asset-tag" name="asset_tag" class="form-control" maxlength="80" value="{{ $createAssetHasErrors ? old('asset_tag') : '' }}" required>
                                @if($createAssetHasErrors)@error('asset_tag')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="create-asset-serial">Serial number</label>
                                <input id="create-asset-serial" name="serial_number" class="form-control" maxlength="100" value="{{ $createAssetHasErrors ? old('serial_number') : '' }}">
                                @if($createAssetHasErrors)@error('serial_number')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="create-asset-name">Equipment name <span class="text-danger">*</span></label>
                                <input id="create-asset-name" name="name" class="form-control" maxlength="180" value="{{ $createAssetHasErrors ? old('name') : '' }}" required>
                                @if($createAssetHasErrors)@error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="create-asset-category">Category <span class="text-danger">*</span></label>
                                <select id="create-asset-category" name="asset_category_id" class="form-select" required>
                                    <option value="">Select a category</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}" @selected($createAssetHasErrors && old('asset_category_id') == $category->id)>{{ $category->name }}</option>
                                    @endforeach
                                </select>
                                @if($createAssetHasErrors)@error('asset_category_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="create-asset-brand">Brand</label>
                                <input id="create-asset-brand" name="brand" class="form-control" maxlength="100" value="{{ $createAssetHasErrors ? old('brand') : '' }}">
                                @if($createAssetHasErrors)@error('brand')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="create-asset-model">Model</label>
                                <input id="create-asset-model" name="model" class="form-control" maxlength="100" value="{{ $createAssetHasErrors ? old('model') : '' }}">
                                @if($createAssetHasErrors)@error('model')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                            </div>

                            <div class="asset-form-section mt-2">Assignment and location</div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="create-asset-department">Department</label>
                                <select id="create-asset-department" name="department_id" class="form-select">
                                    <option value="">Unassigned</option>
                                    @foreach($departments as $department)
                                        <option value="{{ $department->id }}" @selected($createAssetHasErrors && old('department_id') == $department->id)>{{ $department->name }}</option>
                                    @endforeach
                                </select>
                                @if($createAssetHasErrors)@error('department_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="create-asset-user">Assigned user</label>
                                <select id="create-asset-user" name="assigned_user_id" class="form-select">
                                    <option value="">Unassigned</option>
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}" @selected($createAssetHasErrors && old('assigned_user_id') == $user->id)>{{ $user->name }}</option>
                                    @endforeach
                                </select>
                                @if($createAssetHasErrors)@error('assigned_user_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="create-asset-building">Building</label>
                                <input id="create-asset-building" name="building" class="form-control" maxlength="100" value="{{ $createAssetHasErrors ? old('building') : '' }}">
                                @if($createAssetHasErrors)@error('building')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="create-asset-room">Room</label>
                                <input id="create-asset-room" name="room_number" class="form-control" maxlength="40" value="{{ $createAssetHasErrors ? old('room_number') : '' }}">
                                @if($createAssetHasErrors)@error('room_number')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                            </div>

                            <div class="asset-form-section mt-2">Lifecycle and condition</div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="create-asset-acquired">Acquired on</label>
                                <input id="create-asset-acquired" name="acquired_on" type="date" class="form-control" value="{{ $createAssetHasErrors ? old('acquired_on') : '' }}">
                                @if($createAssetHasErrors)@error('acquired_on')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="create-asset-warranty">Warranty expires</label>
                                <input id="create-asset-warranty" name="warranty_expires_on" type="date" class="form-control" value="{{ $createAssetHasErrors ? old('warranty_expires_on') : '' }}">
                                @if($createAssetHasErrors)@error('warranty_expires_on')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="create-asset-condition">Condition <span class="text-danger">*</span></label>
                                <select id="create-asset-condition" name="condition" class="form-select" required>
                                    @foreach(['New', 'Good', 'Fair', 'Needs Repair', 'Unserviceable'] as $condition)
                                        <option @selected(($createAssetHasErrors ? old('condition', 'Good') : 'Good') === $condition)>{{ $condition }}</option>
                                    @endforeach
                                </select>
                                @if($createAssetHasErrors)@error('condition')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="create-asset-status">Status <span class="text-danger">*</span></label>
                                <select id="create-asset-status" name="status" class="form-select" required>
                                    @foreach(['In Service', 'Under Repair', 'In Storage', 'Retired'] as $status)
                                        <option @selected(($createAssetHasErrors ? old('status', 'In Service') : 'In Service') === $status)>{{ $status }}</option>
                                    @endforeach
                                </select>
                                @if($createAssetHasErrors)@error('status')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="create-asset-remarks">Remarks</label>
                                <textarea id="create-asset-remarks" name="remarks" class="form-control" rows="3" maxlength="3000">{{ $createAssetHasErrors ? old('remarks') : '' }}</textarea>
                                @if($createAssetHasErrors)@error('remarks')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer justify-content-end flex-wrap gap-2">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save asset</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade asset-modal" id="editAssetModal" tabindex="-1" aria-labelledby="editAssetModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable modal-fullscreen-lg-down">
            <div class="modal-content">
                <form id="editAssetForm" method="POST" action="{{ $reopenAsset ? route('assets.update', $reopenAsset) : route('assets.index') }}">
                    @csrf
                    @method('PUT')
                    <input id="editingAssetId" type="hidden" name="editing_asset_id" value="{{ $reopenAsset?->id }}">
                    <div class="modal-header align-items-start">
                        <div><h2 class="modal-title" id="editAssetModalTitle">Edit asset record</h2><p>Update inventory details, assignment, condition, and status.</p></div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        @if($reopenAsset && $errors->any())
                            <div class="alert alert-danger" role="alert">Please review the highlighted asset fields and save again.</div>
                        @endif
                        <div class="row g-3">
                            <div class="asset-form-section">Asset identification</div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="edit-asset-tag">Asset tag <span class="text-danger">*</span></label>
                                <input id="edit-asset-tag" name="asset_tag" class="form-control" maxlength="80" value="{{ $reopenAsset ? old('asset_tag', $reopenAsset->asset_tag) : '' }}" required>
                                @if($reopenAsset)@error('asset_tag')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="edit-asset-serial">Serial number</label>
                                <input id="edit-asset-serial" name="serial_number" class="form-control" maxlength="100" value="{{ $reopenAsset ? old('serial_number', $reopenAsset->serial_number) : '' }}">
                                @if($reopenAsset)@error('serial_number')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="edit-asset-name">Equipment name <span class="text-danger">*</span></label>
                                <input id="edit-asset-name" name="name" class="form-control" maxlength="180" value="{{ $reopenAsset ? old('name', $reopenAsset->name) : '' }}" required>
                                @if($reopenAsset)@error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="edit-asset-category">Category <span class="text-danger">*</span></label>
                                <select id="edit-asset-category" name="asset_category_id" class="form-select" required>
                                    <option value="">Select a category</option>
                                    @foreach($editCategories as $category)
                                        <option value="{{ $category->id }}" @selected($reopenAsset && old('asset_category_id', $reopenAsset->asset_category_id) == $category->id)>{{ $category->name }}{{ $category->is_active ? '' : ' (Inactive)' }}</option>
                                    @endforeach
                                </select>
                                @if($reopenAsset)@error('asset_category_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="edit-asset-brand">Brand</label>
                                <input id="edit-asset-brand" name="brand" class="form-control" maxlength="100" value="{{ $reopenAsset ? old('brand', $reopenAsset->brand) : '' }}">
                                @if($reopenAsset)@error('brand')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="edit-asset-model">Model</label>
                                <input id="edit-asset-model" name="model" class="form-control" maxlength="100" value="{{ $reopenAsset ? old('model', $reopenAsset->model) : '' }}">
                                @if($reopenAsset)@error('model')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                            </div>

                            <div class="asset-form-section mt-2">Assignment and location</div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="edit-asset-department">Department</label>
                                <select id="edit-asset-department" name="department_id" class="form-select">
                                    <option value="">Unassigned</option>
                                    @foreach($editDepartments as $department)
                                        <option value="{{ $department->id }}" @selected($reopenAsset && old('department_id', $reopenAsset->department_id) == $department->id)>{{ $department->name }}{{ $department->is_active ? '' : ' (Inactive)' }}</option>
                                    @endforeach
                                </select>
                                @if($reopenAsset)@error('department_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="edit-asset-user">Assigned user</label>
                                <select id="edit-asset-user" name="assigned_user_id" class="form-select">
                                    <option value="">Unassigned</option>
                                    @foreach($editUsers as $user)
                                        <option value="{{ $user->id }}" @selected($reopenAsset && old('assigned_user_id', $reopenAsset->assigned_user_id) == $user->id)>{{ $user->name }}{{ $user->is_active ? '' : ' (Inactive)' }}</option>
                                    @endforeach
                                </select>
                                @if($reopenAsset)@error('assigned_user_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="edit-asset-building">Building</label>
                                <input id="edit-asset-building" name="building" class="form-control" maxlength="100" value="{{ $reopenAsset ? old('building', $reopenAsset->building) : '' }}">
                                @if($reopenAsset)@error('building')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="edit-asset-room">Room</label>
                                <input id="edit-asset-room" name="room_number" class="form-control" maxlength="40" value="{{ $reopenAsset ? old('room_number', $reopenAsset->room_number) : '' }}">
                                @if($reopenAsset)@error('room_number')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                            </div>

                            <div class="asset-form-section mt-2">Lifecycle and condition</div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="edit-asset-acquired">Acquired on</label>
                                <input id="edit-asset-acquired" name="acquired_on" type="date" class="form-control" value="{{ $reopenAsset ? old('acquired_on', $reopenAsset->acquired_on?->format('Y-m-d')) : '' }}">
                                @if($reopenAsset)@error('acquired_on')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="edit-asset-warranty">Warranty expires</label>
                                <input id="edit-asset-warranty" name="warranty_expires_on" type="date" class="form-control" value="{{ $reopenAsset ? old('warranty_expires_on', $reopenAsset->warranty_expires_on?->format('Y-m-d')) : '' }}">
                                @if($reopenAsset)@error('warranty_expires_on')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="edit-asset-condition">Condition <span class="text-danger">*</span></label>
                                <select id="edit-asset-condition" name="condition" class="form-select" required>
                                    @foreach(['New', 'Good', 'Fair', 'Needs Repair', 'Unserviceable'] as $condition)
                                        <option @selected($reopenAsset && old('condition', $reopenAsset->condition) === $condition)>{{ $condition }}</option>
                                    @endforeach
                                </select>
                                @if($reopenAsset)@error('condition')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="edit-asset-status">Status <span class="text-danger">*</span></label>
                                <select id="edit-asset-status" name="status" class="form-select" required>
                                    @foreach(['In Service', 'Under Repair', 'In Storage', 'Retired'] as $status)
                                        <option @selected($reopenAsset && old('status', $reopenAsset->status) === $status)>{{ $status }}</option>
                                    @endforeach
                                </select>
                                @if($reopenAsset)@error('status')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="edit-asset-remarks">Remarks</label>
                                <textarea id="edit-asset-remarks" name="remarks" class="form-control" rows="3" maxlength="3000">{{ $reopenAsset ? old('remarks', $reopenAsset->remarks) : '' }}</textarea>
                                @if($reopenAsset)@error('remarks')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer justify-content-end flex-wrap gap-2">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const editAssetModalElement = document.getElementById('editAssetModal');
        const editAssetForm = document.getElementById('editAssetForm');
        const editAssetInputs = {
            'asset-id': 'editingAssetId',
            'asset-tag': 'edit-asset-tag',
            'serial-number': 'edit-asset-serial',
            'name': 'edit-asset-name',
            'category-id': 'edit-asset-category',
            'brand': 'edit-asset-brand',
            'model': 'edit-asset-model',
            'department-id': 'edit-asset-department',
            'assigned-user-id': 'edit-asset-user',
            'building': 'edit-asset-building',
            'room-number': 'edit-asset-room',
            'acquired-on': 'edit-asset-acquired',
            'warranty-expires-on': 'edit-asset-warranty',
            'condition': 'edit-asset-condition',
            'status': 'edit-asset-status',
            'remarks': 'edit-asset-remarks'
        };

        editAssetModalElement?.addEventListener('show.bs.modal', (event) => {
            const trigger = event.relatedTarget;
            if (!trigger) return;

            editAssetForm.action = trigger.dataset.updateUrl;
            Object.entries(editAssetInputs).forEach(([dataKey, inputId]) => {
                const input = document.getElementById(inputId);
                const datasetKey = dataKey.replace(/-([a-z])/g, (_, letter) => letter.toUpperCase());
                if (input) input.value = trigger.dataset[datasetKey] ?? '';
            });
        });

        const reopenEditAssetModal = @json((bool) $reopenAsset);
        const reopenCreateAssetModal = @json((bool) $createAssetHasErrors);
        if (reopenEditAssetModal) {
            bootstrap.Modal.getOrCreateInstance(editAssetModalElement).show();
        } else if (reopenCreateAssetModal) {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('createAssetModal')).show();
        }
    </script>
@endpush
