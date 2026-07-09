<?php

namespace App\Http\Controllers\Manufacturing;

use App\Http\Controllers\Controller;
use App\Models\ManufacturingBom;
use App\Models\ManufacturingOrder;
use Illuminate\View\View;

class ManufacturingController extends Controller
{
    public function index(): View
    {
        $kpis = [
            'boms' => ManufacturingBom::query()->where('active', true)->count(),
            'boms_total' => ManufacturingBom::query()->count(),
            'orders_draft' => ManufacturingOrder::query()->where('status', ManufacturingOrder::STATUS_DRAFT)->count(),
            'orders_done_month' => ManufacturingOrder::query()
                ->where('status', ManufacturingOrder::STATUS_DONE)
                ->where('completed_at', '>=', now()->startOfMonth())
                ->count(),
        ];

        $recentOrders = ManufacturingOrder::query()
            ->with(['bom.finishedProduct:id,sku,name', 'user:id,name'])
            ->latest()
            ->limit(8)
            ->get();

        return view('manufacturing.index', compact('kpis', 'recentOrders'));
    }
}
