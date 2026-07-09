<?php

namespace App\Http\Controllers\GuestRoom;

use App\Http\Controllers\Controller;
use App\Models\RoomBooking;
use App\Models\RoomCategory;
use App\Models\RoomPersonType;
use App\Models\RoomRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RoomRateController extends Controller
{
    public function index()
    {
        $categories = RoomCategory::query()
            ->with(['rates' => fn ($q) => $q->orderBy('person_type')])
            ->withCount('guestRooms')
            ->orderedForRoomList()
            ->get();

        $personTypes = RoomPersonType::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('guest-rooms.rates.index', compact('categories', 'personTypes'));
    }

    public function storeCategory(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', $this->uniqueCategoryNameRule()],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'active' => ['nullable', 'boolean'],
        ], [
            'name.unique' => 'This category name already exists.',
        ]);
        $data['active'] = $request->boolean('active', true);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        RoomCategory::query()->create($data);

        return redirect()->route('guest-rooms.rates.index')->with('success', 'Category added.');
    }

    public function updateCategory(Request $request, RoomCategory $category)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', $this->uniqueCategoryNameRule($category->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'active' => ['nullable', 'boolean'],
        ], [
            'name.unique' => 'This category name already exists.',
        ]);
        $data['active'] = $request->boolean('active', true);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        $category->update($data);

        return redirect()->route('guest-rooms.rates.index')->with('success', 'Category updated.');
    }

    public function destroyCategory(RoomCategory $category)
    {
        if ($category->guestRooms()->exists() || $category->rates()->exists()) {
            return back()->with('error', 'Cannot delete category that has rooms or rates.');
        }
        $category->delete();

        return redirect()->route('guest-rooms.rates.index')->with('success', 'Category deleted.');
    }

    public function storePersonType(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', $this->uniquePersonTypeNameRule()],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'active' => ['nullable', 'boolean'],
        ], [
            'name.unique' => 'This guest type already exists.',
        ]);
        $data['active'] = $request->boolean('active', true);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        RoomPersonType::query()->create($data);

        return redirect()->route('guest-rooms.rates.index')->with('success', 'Guest type added.');
    }

    public function updatePersonType(Request $request, RoomPersonType $personType)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', $this->uniquePersonTypeNameRule($personType->id)],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'active' => ['nullable', 'boolean'],
        ], [
            'name.unique' => 'This guest type already exists.',
        ]);
        $data['active'] = $request->boolean('active', true);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        $oldName = $personType->name;
        $personType->update($data);

        if ($oldName !== $personType->name) {
            RoomRate::query()->where('person_type', $oldName)->update(['person_type' => $personType->name]);
            RoomBooking::query()->where('person_type', $oldName)->update(['person_type' => $personType->name]);
        }

        return redirect()->route('guest-rooms.rates.index')->with('success', 'Guest type updated.');
    }

    public function destroyPersonType(RoomPersonType $personType)
    {
        if ($personType->isInUse()) {
            return back()->with('error', 'Cannot delete guest type that is used in rates or bookings.');
        }
        $personType->delete();

        return redirect()->route('guest-rooms.rates.index')->with('success', 'Guest type deleted.');
    }

    public function lookup(Request $request)
    {
        $data = $request->validate([
            'room_category_id' => ['required', 'exists:tenant.room_categories,id'],
            'person_type' => ['required', 'string', 'max:60', $this->existsPersonTypeRule()],
        ]);

        $rate = RoomRate::findForBooking((int) $data['room_category_id'], $data['person_type']);

        if (! $rate) {
            return response()->json(['found' => false, 'message' => 'No rate found for this category and guest type.']);
        }

        return response()->json([
            'found' => true,
            'id' => $rate->id,
            'person_type' => $rate->person_type,
            'room_rent' => $rate->room_rent,
            'electric_charges' => $rate->electric_charges,
            'gas_charges' => $rate->gas_charges,
            'media_charges' => $rate->media_charges,
            'total' => $rate->total,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedRate($request);
        $data['active'] = $request->boolean('active', true);
        $data['rate_type'] = 'nightly';
        $data['room_type_id'] = null;

        RoomRate::query()->create($data);

        return redirect()->route('guest-rooms.rates.index')->with('success', 'Rate added.');
    }

    public function update(Request $request, RoomRate $rate)
    {
        $data = $this->validatedRate($request, $rate->id);
        $data['active'] = $request->boolean('active', true);
        $data['rate_type'] = 'nightly';
        $data['room_type_id'] = null;

        $rate->update($data);

        return redirect()->route('guest-rooms.rates.index')->with('success', 'Rate updated.');
    }

    public function destroy(RoomRate $rate)
    {
        $rate->delete();

        return redirect()->route('guest-rooms.rates.index')->with('success', 'Rate deleted.');
    }

    private function validatedRate(Request $request, ?int $ignoreRateId = null): array
    {
        $categoryId = $request->input('room_category_id');

        return $request->validate([
            'room_category_id' => ['required', 'exists:tenant.room_categories,id'],
            'person_type' => [
                'required',
                'string',
                'max:60',
                $this->existsPersonTypeRule(),
                Rule::unique('tenant.room_rates', 'person_type')
                    ->where(fn ($q) => $q->where('room_category_id', $categoryId))
                    ->ignore($ignoreRateId),
            ],
            'room_rent' => ['required', 'numeric', 'min:0'],
            'electric_charges' => ['nullable', 'numeric', 'min:0'],
            'gas_charges' => ['nullable', 'numeric', 'min:0'],
            'media_charges' => ['nullable', 'numeric', 'min:0'],
        ], [
            'person_type.unique' => 'Rate for this guest type already exists in this category.',
        ]);
    }

    private function uniqueCategoryNameRule(?int $ignoreId = null)
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

    private function uniquePersonTypeNameRule(?int $ignoreId = null)
    {
        $rule = Rule::unique('tenant.room_person_types', 'name');
        $companyId = current_company_id();
        if ($companyId !== null) {
            $rule = $rule->where(fn ($query) => $query->where('company_id', $companyId));
        }
        if ($ignoreId !== null) {
            $rule = $rule->ignore($ignoreId);
        }

        return $rule;
    }

    private function existsPersonTypeRule()
    {
        $rule = Rule::exists('tenant.room_person_types', 'name');
        $companyId = current_company_id();
        if ($companyId !== null) {
            $rule = $rule->where(fn ($query) => $query->where('company_id', $companyId));
        }

        return $rule;
    }
}
