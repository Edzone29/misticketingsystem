<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Department;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AssetController extends Controller
{
    public function show(Asset $asset)
    {
        $asset->load(['category', 'department', 'assignedUser', 'tickets', 'maintenanceRecords', 'maintenanceSchedules']);

        return view('assets.show', compact('asset'));
    }

    public function index(Request $request)
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['In Service', 'Under Repair', 'In Storage', 'Retired'])],
            'asset_category_id' => ['nullable', 'integer', Rule::exists('asset_categories', 'id')],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
        ]);
        $assets = Asset::with(['category', 'department', 'assignedUser'])
            ->when($request->search, fn ($query, $search) => $query->where(fn ($filter) => $filter
                ->where('asset_tag', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->orWhere('serial_number', 'like', "%{$search}%")))
            ->when($request->status, fn ($query, $status) => $query->where('status', $status))
            ->when($request->asset_category_id, fn ($query, $categoryId) => $query->where('asset_category_id', $categoryId))
            ->when($request->department_id, fn ($query, $departmentId) => $query->where('department_id', $departmentId))
            ->orderBy('asset_tag')
            ->paginate(20)
            ->withQueryString();
        $pageAssets = $assets->getCollection();
        $categoryIds = $pageAssets->pluck('asset_category_id')->filter()->unique();
        $departmentIds = $pageAssets->pluck('department_id')->filter()->unique();
        $userIds = $pageAssets->pluck('assigned_user_id')->filter()->unique();
        $categories = AssetCategory::where('is_active', true)->orderBy('name')->get();
        $departments = Department::where('is_active', true)->orderBy('name')->get();
        $filterCategories = AssetCategory::orderBy('name')->get();
        $filterDepartments = Department::orderBy('name')->get();
        $users = User::where('is_active', true)->orderBy('last_name')->get();
        $editCategories = AssetCategory::where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', $categoryIds))->orderBy('name')->get();
        $editDepartments = Department::where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', $departmentIds))->orderBy('name')->get();
        $editUsers = User::where(fn ($query) => $query->where('is_active', true)->orWhereIn('id', $userIds))->orderBy('last_name')->get();

        return view('assets.index', compact('assets', 'categories', 'departments', 'users', 'editCategories', 'editDepartments', 'editUsers', 'filterCategories', 'filterDepartments'));
    }

    public function store(Request $request, Audit $audit)
    {
        $asset = Asset::create($this->validated($request));
        $audit->record('asset.created', $asset);

        return back()->with('success', 'Asset record created.');
    }

    public function update(Request $request, Asset $asset, Audit $audit)
    {
        $asset->update($this->validated($request, $asset));
        $audit->record('asset.updated', $asset);

        return back()->with('success', 'Asset record updated.');
    }

    private function validated(Request $request, ?Asset $asset = null): array
    {
        $activeOrCurrent = fn (string $table, ?int $currentId) => Rule::exists($table, 'id')->where(fn ($query) => $query
            ->where('is_active', true)
            ->when($currentId, fn ($query, $id) => $query->orWhere('id', $id)));

        return $request->validate([
            'asset_tag' => ['required', 'string', 'max:80', Rule::unique('assets')->ignore($asset?->id)],
            'serial_number' => ['nullable', 'string', 'max:100', Rule::unique('assets')->ignore($asset?->id)],
            'name' => ['required', 'string', 'max:180'],
            'asset_category_id' => ['required', $activeOrCurrent('asset_categories', $asset?->asset_category_id)],
            'brand' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'department_id' => ['nullable', $activeOrCurrent('departments', $asset?->department_id)],
            'assigned_user_id' => ['nullable', $activeOrCurrent('users', $asset?->assigned_user_id)],
            'building' => ['nullable', 'string', 'max:100'],
            'room_number' => ['nullable', 'string', 'max:40'],
            'acquired_on' => ['nullable', 'date'],
            'warranty_expires_on' => ['nullable', 'date'],
            'condition' => ['required', Rule::in(['New', 'Good', 'Fair', 'Needs Repair', 'Unserviceable'])],
            'status' => ['required', Rule::in(['In Service', 'Under Repair', 'In Storage', 'Retired'])],
            'remarks' => ['nullable', 'string', 'max:3000'],
        ]);
    }
}
