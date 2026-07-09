<?php

namespace App\Http\Controllers\OrderStatus;

use App\Http\Controllers\Controller;
use App\Services\KitchenService;
use Illuminate\View\View;

class OrderStatusController extends Controller
{
    public function __construct(
        private readonly KitchenService $kitchen
    ) {}

    public function index(): View
    {
        $orders = $this->kitchen->ordersForCafeStatusScreen();

        return view('order-status.index', [
            'orders' => $orders,
            'activeCount' => $orders->count(),
        ]);
    }

    public function board(): View
    {
        $orders = $this->kitchen->ordersForCafeStatusScreen();

        return view('order-status.partials.board', compact('orders'));
    }
}
