<?php

namespace App\Http\Controllers;

use App\Models\TicketCategory;
use App\Models\TicketSubcategory;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TicketCategoryController extends Controller
{
    public function index()
    {
        return view('categories.index', ['categories' => TicketCategory::with('subcategories')->withCount('tickets')->orderBy('name')->get()]);
    }

    public function store(Request $request, Audit $audit)
    {
        $d = $request->validate(['name' => ['required', 'string', 'max:180', 'unique:ticket_categories,name'], 'description' => ['nullable', 'string', 'max:2000']]);
        $category = TicketCategory::create($d + ['is_active' => true]);
        $audit->record('ticket_category.created', $category);

        return back()->with('success', 'Category created.');
    }

    public function update(Request $request, TicketCategory $category, Audit $audit)
    {
        $d = $request->validate(['name' => ['required', 'string', 'max:180', Rule::unique('ticket_categories')->ignore($category->id)], 'description' => ['nullable', 'string', 'max:2000'], 'is_active' => ['required', 'boolean']]);
        $category->update($d);
        $audit->record('ticket_category.updated', $category);

        return back()->with('success', 'Category updated.');
    }

    public function storeSubcategory(Request $request, Audit $audit)
    {
        $d = $request->validate(['ticket_category_id' => ['required', 'exists:ticket_categories,id'], 'name' => ['required', 'string', 'max:180']]);
        if (TicketSubcategory::where('ticket_category_id', $d['ticket_category_id'])->where('name', $d['name'])->exists()) {
            return back()->withErrors(['name' => 'This subcategory already exists for the selected category.']);
        } $subcategory = TicketSubcategory::create($d + ['is_active' => true]);
        $audit->record('ticket_subcategory.created', $subcategory);

        return back()->with('success', 'Subcategory created.');
    }

    public function updateSubcategory(Request $request, TicketSubcategory $subcategory, Audit $audit)
    {
        $d = $request->validate(['name' => ['required', 'string', 'max:180'], 'is_active' => ['required', 'boolean']]);
        $subcategory->update($d);
        $audit->record('ticket_subcategory.updated', $subcategory);

        return back()->with('success','Subcategory updated.');
    }
}
