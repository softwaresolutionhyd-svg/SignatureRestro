<?php

namespace App\Http\Controllers\Expense;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use App\Models\Setting;
use Illuminate\Http\Request;

class ExpenseCategoryController extends Controller
{
    public function index()
    {
        $categories = ExpenseCategory::withCount('expenses')->latest()->paginate(Setting::pageSize('expenses_categories_per_page', 20));
        return view('expenses.categories.index', compact('categories'));
    }

    public function create()
    {
        return view('expenses.categories.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:120|unique:tenant.expense_categories,name',
            'description' => 'nullable|string|max:255',
            'active'      => 'nullable|boolean',
        ]);
        $data['active'] = $request->boolean('active', true);

        ExpenseCategory::create($data);

        return redirect()->route('expenses.categories.index')
            ->with('success', 'Category created successfully.');
    }

    public function edit(ExpenseCategory $category)
    {
        return view('expenses.categories.edit', compact('category'));
    }

    public function update(Request $request, ExpenseCategory $category)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:120|unique:tenant.expense_categories,name,' . $category->id,
            'description' => 'nullable|string|max:255',
            'active'      => 'nullable|boolean',
        ]);
        $data['active'] = $request->boolean('active', true);

        $category->update($data);

        return redirect()->route('expenses.categories.index')
            ->with('success', 'Category updated successfully.');
    }

    public function destroy(ExpenseCategory $category)
    {
        if ($category->expenses()->exists()) {
            return back()->with('error', 'Cannot delete category that has expenses linked to it.');
        }
        $category->delete();
        return redirect()->route('expenses.categories.index')
            ->with('success', 'Category deleted.');
    }
}
