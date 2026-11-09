<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use App\Services\Audit;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index()
    {
        return view('roles.index', ['roles' => Role::with('permissions')->withCount('users')->orderBy('id')->get(), 'permissions' => Permission::orderBy('name')->get()]);
    }

    public function store(Request $request, Audit $audit)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:60', 'regex:/^[a-z][a-z0-9_]*$/', 'unique:roles,name'], 'label' => ['required', 'string', 'max:120']]);
        $role = Role::create($data);
        $audit->record('role.created', $role);

        return back()->with('success', 'Role created. Assign permissions before assigning this role to a user.');
    }

    public function updatePermissions(Request $request, Role $role, Audit $audit)
    {
        $data = $request->validate(['permission_ids' => ['nullable', 'array'], 'permission_ids.*' => ['integer', 'exists:permissions,id']]);
        $role->permissions()->sync($data['permission_ids'] ?? []);
        $audit->record('role.permissions_changed', $role, ['permission_ids' => $data['permission_ids'] ?? []]);

        return back()->with('success', 'Role permissions updated.');
    }
}
