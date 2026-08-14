<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryProduct;
use App\Models\MenuDeal;
use App\Models\MenuDealItem;
use App\Models\Setting;
use App\Services\Sync\SyncAwareDelete;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DealController extends Controller
{
    public function __construct()
    {
        MenuDeal::ensureSchema();
    }

    public function index(): View
    {
        MenuDeal::ensureSchema();

        $deals = MenuDeal::query()
            ->with(['items.product:id,sku,name,uom,price', 'product:id,sku,name,active'])
            ->withCount('items')
            ->latest()
            ->paginate(Setting::pageSize('inventory_products_per_page', 20));

        return view('inventory.deals.index', compact('deals'));
    }

    public function create(): View
    {
        MenuDeal::ensureSchema();

        return view('inventory.deals.form', [
            'deal' => null,
            'menuProducts' => $this->menuProductsForPicker(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        MenuDeal::ensureSchema();
        $data = $this->validated($request);

        $deal = DB::connection('tenant')->transaction(function () use ($data) {
            $deal = MenuDeal::create([
                'name' => $data['name'],
                'price' => $data['price'],
                'is_permanent' => $data['is_permanent'],
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'],
                'active' => $data['active'],
                'created_by' => Auth::id(),
            ]);
            $this->syncItems($deal, $data['items']);
            $this->syncPosProduct($deal);

            return $deal;
        });

        MenuDeal::flushProductCache();

        return redirect()->route('inventory.deals.index')->with('status', 'Deal save ho gayi: '.$deal->name);
    }

    public function edit(MenuDeal $deal): View
    {
        MenuDeal::ensureSchema();
        $deal->load(['items.product:id,sku,name,uom,price']);

        return view('inventory.deals.form', [
            'deal' => $deal,
            'menuProducts' => $this->menuProductsForPicker((int) $deal->product_id),
        ]);
    }

    public function update(Request $request, MenuDeal $deal): RedirectResponse
    {
        MenuDeal::ensureSchema();
        $data = $this->validated($request, (int) $deal->id);

        DB::connection('tenant')->transaction(function () use ($deal, $data) {
            $deal->update([
                'name' => $data['name'],
                'price' => $data['price'],
                'is_permanent' => $data['is_permanent'],
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'],
                'active' => $data['active'],
            ]);
            SyncAwareDelete::query(MenuDealItem::query()->where('deal_id', $deal->id));
            $this->syncItems($deal, $data['items']);
            $this->syncPosProduct($deal->fresh(['items.product']));
        });

        MenuDeal::flushProductCache();

        return redirect()->route('inventory.deals.index')->with('status', 'Deal update ho gayi.');
    }

    public function destroy(MenuDeal $deal): RedirectResponse
    {
        MenuDeal::ensureSchema();

        DB::connection('tenant')->transaction(function () use ($deal) {
            $product = $deal->product;
            SyncAwareDelete::query(MenuDealItem::query()->where('deal_id', $deal->id));
            $deal->delete();
            if ($product) {
                $product->update([
                    'active' => false,
                    'for_pos' => false,
                ]);
            }
        });

        MenuDeal::flushProductCache();

        return redirect()->route('inventory.deals.index')->with('status', 'Deal hata di gayi. POS item inactive ho gaya.');
    }

    /**
     * @return array{
     *   name: string,
     *   price: float,
     *   is_permanent: bool,
     *   starts_at: ?\Illuminate\Support\Carbon,
     *   ends_at: ?\Illuminate\Support\Carbon,
     *   active: bool,
     *   items: list<array{product_id: int, qty: float}>
     * }
     */
    private function validated(Request $request, ?int $dealId = null): array
    {
        $dealProductIds = MenuDeal::query()
            ->when($dealId, fn ($q) => $q->whereKeyNot($dealId))
            ->whereNotNull('product_id')
            ->pluck('product_id')
            ->all();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'price' => ['required', 'numeric', 'min:0'],
            'duration_type' => ['required', 'in:permanent,scheduled'],
            'starts_at' => ['nullable', 'date', 'required_if:duration_type,scheduled'],
            'ends_at' => ['nullable', 'date', 'required_if:duration_type,scheduled', 'after_or_equal:starts_at'],
            'active' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required',
                'integer',
                Rule::exists('tenant.inventory_products', 'id'),
                Rule::notIn($dealProductIds),
            ],
            'items.*.qty' => ['required', 'numeric', 'min:0.001'],
        ]);

        $ids = array_map('intval', array_column($data['items'], 'product_id'));
        if (count($ids) !== count(array_unique($ids))) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'items' => 'Ek menu item deal mein ek hi dafa aa sakta hai.',
            ]);
        }

        $isPermanent = ($data['duration_type'] ?? 'permanent') === 'permanent';
        $startsAt = null;
        $endsAt = null;
        if (! $isPermanent) {
            $startsAt = \Illuminate\Support\Carbon::parse($data['starts_at'])->startOfDay();
            $endsAt = \Illuminate\Support\Carbon::parse($data['ends_at'])->endOfDay();
        }

        return [
            'name' => trim((string) $data['name']),
            'price' => round((float) $data['price'], 2),
            'is_permanent' => $isPermanent,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'active' => $request->boolean('active'),
            'items' => array_values(array_map(static fn ($row) => [
                'product_id' => (int) $row['product_id'],
                'qty' => (float) $row['qty'],
            ], $data['items'])),
        ];
    }

    /**
     * @param  list<array{product_id: int, qty: float}>  $items
     */
    private function syncItems(MenuDeal $deal, array $items): void
    {
        foreach ($items as $i => $row) {
            MenuDealItem::create([
                'deal_id' => $deal->id,
                'product_id' => $row['product_id'],
                'qty' => $row['qty'],
                'sort_order' => $i,
            ]);
        }
    }

    private function syncPosProduct(MenuDeal $deal): void
    {
        $deal->loadMissing('items.product');
        $category = MenuDeal::dealsCategory();
        $onSaleToggle = (bool) $deal->active;
        $cost = 0.0;
        foreach ($deal->items as $line) {
            $cost += (float) ($line->product?->cost ?? 0) * (float) $line->qty;
        }

        $uom = (string) ($deal->items->first()?->product?->uom ?: 'Pcs');
        $payload = [
            'name' => $deal->name,
            'price' => $deal->price,
            'cost' => round($cost, 2),
            'category_id' => $category->id,
            'uom' => $uom,
            'active' => $onSaleToggle,
            'for_pos' => $onSaleToggle,
            'for_purchase' => false,
            'qty_on_hand' => 0,
            'reorder_level' => 0,
        ];

        if ($deal->product_id) {
            $product = InventoryProduct::query()->find($deal->product_id);
            if ($product) {
                $product->update($payload);
                $deal->update(['sku' => $product->sku]);

                return;
            }
        }

        $sku = InventoryProduct::generateNextSku('DEAL');
        $product = InventoryProduct::create($payload + ['sku' => $sku]);
        $deal->update([
            'product_id' => $product->id,
            'sku' => $sku,
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, InventoryProduct>
     */
    private function menuProductsForPicker(?int $excludeDealProductId = null)
    {
        $dealProductIds = MenuDeal::query()
            ->whereNotNull('product_id')
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        if ($excludeDealProductId) {
            $dealProductIds[] = $excludeDealProductId;
        }

        return InventoryProduct::query()
            ->where('active', true)
            ->where('for_pos', true)
            ->when($dealProductIds !== [], fn ($q) => $q->whereNotIn('id', $dealProductIds))
            ->orderBy('name')
            ->get(['id', 'sku', 'name', 'uom', 'price']);
    }
}
