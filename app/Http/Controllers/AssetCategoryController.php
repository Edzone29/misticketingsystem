<?php

namespace App\Http\Controllers;

use App\Models\AssetCategory;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AssetCategoryController extends Controller
{
    public function index()
    {
        return view('assets.categories', ['categories' => AssetCategory::withCount('assets')->orderBy('name')->get()]);
    }

    public function store(Request $request, Audit $audit)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120', 'unique:asset_categories,name']]);
        $category = AssetCategory::create($data + ['is_active' => true]);
        $audit->record('asset_category.created', $category);

        return back()->with('success', 'Asset category created.');
    }

    public function update(Request $request, AssetCategory $category, Audit $audit)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120', Rule::unique('asset_categories')->ignore($category->id)], 'is_active' => ['required', 'boolean']]);
        $category->update($data);
        $audit->record('asset_category.updated', $category, ['is_active' => $category->is_active]);

        return back()->with('success', 'Asset category updated.');
    }
}
