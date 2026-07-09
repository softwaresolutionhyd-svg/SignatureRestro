<?php

namespace App\Http\Controllers\GuestRoom;

use App\Http\Controllers\Controller;
use App\Models\RoomCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoomCategoryController extends Controller
{
    public function index()
    {
        return redirect()->route('guest-rooms.rates.index');
    }

    public function create()
    {
        return view('guest-rooms.categories.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', $this->uniqueNameRule()],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'active' => ['nullable', 'boolean'],
        ], [
            'name.unique' => 'This category name already exists. Choose a different name or edit the existing one.',
        ]);
        $data['active'] = $request->boolean('active', true);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        RoomCategory::query()->create($data);

        return redirect()->route('guest-rooms.categories.index')->with('success', 'Room category created.');
    }

    public function edit(RoomCategory $category)
    {
        return view('guest-rooms.categories.edit', compact('category'));
    }

    public function update(Request $request, RoomCategory $category)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', $this->uniqueNameRule($category->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'active' => ['nullable', 'boolean'],
        ], [
            'name.unique' => 'This category name already exists. Choose a different name.',
        ]);
        $data['active'] = $request->boolean('active', true);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        $category->update($data);

        return redirect()->route('guest-rooms.categories.index')->with('success', 'Room category updated.');
    }

    public function destroy(RoomCategory $category)
    {
        if ($category->guestRooms()->exists() || $category->rates()->exists()) {
            return back()->with('error', 'Cannot delete category linked to rooms or rates.');
        }
        $category->delete();

        return redirect()->route('guest-rooms.categories.index')->with('success', 'Room category deleted.');
    }

    private function uniqueNameRule(?int $ignoreId = null)
    {
        $rule = Rule::unique('tenant.room_categories', 'name');
        $companyId = current_company_id();
        if ($companyId !== null) {
            $rule = $rule->where(fn ($query) => $query->where('company_id', $companyId));
        }
        if ($ignoreId !== null) {
            $rule = $rule->ignore($ignoreId);
        }

        return $rule;
    }
}
