<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryCategory;
use App\Models\Setting;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = InventoryCategory::query()
            ->with('parent:id,name')
            ->orderBy('name')
            ->paginate(Setting::pageSize('inventory_categories_per_page', 20));

        return view('inventory.categories.index', compact('categories'));
    }

    public function create()
    {
        $parents = InventoryCategory::query()
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('inventory.categories.create', compact('parents'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'parent_id' => ['nullable', 'integer', 'exists:tenant.inventory_categories,id'],
        ]);

        $this->assertValidParentCategory($data['parent_id'] ?? null);

        InventoryCategory::create($data);

        return redirect()->route('inventory.categories.index')->with('status', 'Category created.');
    }

    public function edit(InventoryCategory $category)
    {
        $parents = InventoryCategory::query()
            ->whereNull('parent_id')
            ->whereKeyNot($category->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('inventory.categories.edit', compact('category', 'parents'));
    }

    public function update(Request $request, InventoryCategory $category)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'parent_id' => ['nullable', 'integer', 'exists:tenant.inventory_categories,id', 'not_in:'.$category->id],
        ]);

        $this->assertValidParentCategory($data['parent_id'] ?? null);

        $category->update($data);

        return redirect()->route('inventory.categories.index')->with('status', 'Category updated.');
    }

    public function destroy(InventoryCategory $category)
    {
        $category->delete();
        return redirect()->route('inventory.categories.index')->with('status', 'Category deleted.');
    }

    private function assertValidParentCategory(?int $parentId): void
    {
        if (! $parentId) {
            return;
        }

        $parent = InventoryCategory::query()->find($parentId);
        if (! $parent || $parent->parent_id !== null) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'parent_id' => 'Sub-category ko parent nahi bana sakte — sirf main category choose karein.',
            ]);
        }
    }
}
