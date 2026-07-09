<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\Setting;
use App\Services\PurchaseOrderReceiveService;
use Illuminate\Http\Request;

class StockInController extends Controller
{
    public function index(Request $request)
    {
        $orders = PurchaseOrder::query()
            ->with('vendor:id,name')
            ->where('status', 'confirmed')
            ->latest('confirmed_at')
            ->paginate(Setting::pageSize('purchase_orders_per_page', 20))
            ->withQueryString();

        return view('inventory.stock-in.index', compact('orders'));
    }

    public function receive(PurchaseOrder $order, PurchaseOrderReceiveService $receiver)
    {
        abort_unless($order->status === 'confirmed', 404);

        $receiver->receive($order);

        return redirect()
            ->route('inventory.stock-in.index')
            ->with('status', "PO {$order->number} received — stock updated.");
    }
}
