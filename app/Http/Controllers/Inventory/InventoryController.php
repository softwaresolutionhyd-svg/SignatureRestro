<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryCategory;
use App\Models\InventoryMove;
use App\Models\InventoryProduct;
use App\Models\Setting;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index()
    {
        $kpis = [
            'products'        => InventoryProduct::query()->count(),
            'active_products' => InventoryProduct::query()->where('active', true)->count(),
            'on_hand_total'   => (float) InventoryProduct::query()->sum('qty_on_hand'),
            'moves_today'     => InventoryMove::query()->whereDate('created_at', now()->toDateString())->count(),
            'low_stock'       => InventoryProduct::where('active', true)->where('for_purchase', true)->where('reorder_level', '>', 0)->whereRaw('qty_on_hand <= reorder_level')->excludingActiveBomFinishedProducts()->count(),
            'out_of_stock'    => InventoryProduct::where('active', true)->where('for_purchase', true)->where('qty_on_hand', '<=', 0)->count(),
        ];

        $recentMoves = InventoryMove::query()
            ->with(['product:id,sku,name,uom', 'user:id,name'])
            ->latest()
            ->limit(10)
            ->get();

        return view('inventory.index', compact('kpis', 'recentMoves'));
    }

    public function lowStock(Request $request)
    {
        $currency   = Setting::get('currency_symbol', 'Rs.');
        $filter     = $request->input('filter', 'low'); // low | zero | all
        $category   = $request->input('category_id', '');
        $categories = InventoryCategory::orderBy('name')->get(['id','name']);

        $q = InventoryProduct::with('category')->where('active', true)->where('for_purchase', true)->excludingActiveBomFinishedProducts();

        if ($category) {
            $q->where('category_id', $category);
        }

        switch ($filter) {
            case 'zero':
                $q->where('qty_on_hand', '<=', 0);
                break;
            case 'all':
                break;
            default: // low = at/below reorder level (where reorder_level is set)
                $q->where(function($sub) {
                    $sub->where(fn($x) => $x->where('reorder_level', '>', 0)->whereRaw('qty_on_hand <= reorder_level'))
                        ->orWhere('qty_on_hand', '<=', 0);
                });
                break;
        }

        $products = $q->orderByRaw('(qty_on_hand / NULLIF(reorder_level, 0)) ASC')
                      ->orderBy('qty_on_hand')
                      ->get();

        // KPIs
        $allActive   = InventoryProduct::where('active', true)->where('for_purchase', true)->excludingActiveBomFinishedProducts();
        $kpiLow      = (clone $allActive)->where('reorder_level', '>', 0)->whereRaw('qty_on_hand <= reorder_level')->count();
        $kpiZero     = (clone $allActive)->where('qty_on_hand', '<=', 0)->count();
        $kpiCritical = (clone $allActive)->where('reorder_level', '>', 0)->whereRaw('qty_on_hand <= reorder_level * 0.5')->count();
        $kpiOk       = (clone $allActive)->where(fn ($x) => $x->where('reorder_level', 0)->orWhereRaw('qty_on_hand > reorder_level'))->where('qty_on_hand', '>', 0)->count();

        return view('inventory.low-stock', compact(
            'products', 'filter', 'category', 'categories', 'currency',
            'kpiLow', 'kpiZero', 'kpiCritical', 'kpiOk'
        ));
    }
}
