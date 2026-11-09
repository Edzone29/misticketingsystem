<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $departments = Department::with('head:id,name')->with(['users' => fn ($q) => $q->select(['id', 'department_id', 'name'])->where('is_active', true)->orderBy('name')])->withCount(['users as active_users_count' => fn ($q) => $q->where('is_active', true)])->when($request->search, fn ($q, $s) => $q->where(fn ($b) => $b->where('name', 'like', "%$s%")->orWhere('code', 'like', "%$s%")))->orderBy('name')->paginate(15)->withQueryString();

        return view('departments.index', compact('departments'));
    }

    public function store(Request $request, Audit $audit)
    {
        $d = $request->validate(['code' => ['required', 'string', 'max:20', 'unique:departments,code'], 'name' => ['required', 'string', 'max:255', 'unique:departments,name'], 'description' => ['nullable', 'string', 'max:2000']]);
        $department = Department::create($d);
        $audit->record('department.created', $department);

        return back()->with('success', 'Department created.');
    }

    public function update(Request $request, Department $department, Audit $audit)
    {
        $d = $request->validate(['code' => ['required', 'string', 'max:20', Rule::unique('departments')->ignore($department->id)], 'name' => ['required', 'string', 'max:255', Rule::unique('departments')->ignore($department->id)], 'description' => ['nullable', 'string', 'max:2000'], 'head_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($q) => $q->where('department_id', $department->id)->where('is_active', true))], 'is_active' => ['required', 'boolean']]);
        $department->update($d);
        $audit->record('department.updated', $department);

        return back()->with('success', 'Department updated.');
    }

    public function destroy(Department $department, Audit $audit)
    {
        $department->update(['is_active' => false]);
        $audit->record('department.deactivated', $department);

        return back()->with('success', 'Department deactivated.');
    }
}
