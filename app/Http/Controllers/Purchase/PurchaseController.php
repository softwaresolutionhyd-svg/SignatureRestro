<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\PurchaseVendor;

class PurchaseController extends Controller
{
    public function index()
    {
        $kpis = [
            'vendors' => PurchaseVendor::query()->count(),
            'rfqs' => PurchaseOrder::query()->where('status', 'rfq')->count(),
            'confirmed' => PurchaseOrder::query()->where('status', 'confirmed')->count(),
            'received' => PurchaseOrder::query()->where('status', 'received')->count(),
        ];

        $recentOrders = PurchaseOrder::query()
            ->with('vendor:id,name')
            ->latest()
            ->limit(10)
            ->get();

        return view('purchase.index', compact('kpis', 'recentOrders'));
    }
}
