<?php

namespace App\Http\Controllers\Kitchen;

use App\Http\Controllers\Controller;
use App\Models\PosOrder;
use App\Models\PosOrderItem;
use App\Services\KitchenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KitchenController extends Controller
{
    public function __construct(
        private readonly KitchenService $kitchen
    ) {}

    public function index(): View
    {
        $orders = $this->kitchen->ordersForFreeBoard();
        $activeCount = $orders->count();
        $pendingDishes = $this->kitchen->pendingDishSummary();
        $requiredIngredients = $this->kitchen->pendingRecipeConsumption();

        return view('kitchen.index', compact('orders', 'activeCount', 'pendingDishes', 'requiredIngredients'));
    }

    public function board(): View
    {
        $orders = $this->kitchen->ordersForFreeBoard();

        return view('kitchen.partials.board', compact('orders'));
    }

    public function summary(): View
    {
        return view('kitchen.partials.summary', [
            'pendingDishes' => $this->kitchen->pendingDishSummary(),
            'requiredIngredients' => $this->kitchen->pendingRecipeConsumption(),
        ]);
    }

    public function todayConsumption(): View
    {
        return view('kitchen.partials.today-consumption', [
            'todayConsumption' => $this->kitchen->todayRecipeConsumption(),
            'todayLabel' => now()->format('d M Y'),
        ]);
    }

    public function complete(Request $request, PosOrder $order): JsonResponse|RedirectResponse
    {
        return $this->status($request, $order, PosOrder::KITCHEN_STATUS_READY);
    }

    public function status(Request $request, PosOrder $order, string $step): JsonResponse|RedirectResponse
    {
        $map = [
            'preparing' => PosOrder::KITCHEN_STATUS_PREPARING,
            'ready' => PosOrder::KITCHEN_STATUS_READY,
            'complete' => PosOrder::KITCHEN_STATUS_READY,
            'served' => PosOrder::KITCHEN_STATUS_SERVED,
        ];

        if (! isset($map[$step])) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => 'Invalid status step.'], 422);
            }

            return back()->with('error', 'Invalid status step.');
        }

        try {
            $fresh = $this->kitchen->advanceKitchenStatus($order, $map[$step]);
        } catch (\Throwable $e) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'status' => $fresh->kitchenStatusKey(),
                'removed' => $map[$step] === PosOrder::KITCHEN_STATUS_SERVED,
            ]);
        }

        $message = match ($map[$step]) {
            PosOrder::KITCHEN_STATUS_PREPARING => 'Order preparing — cafe screen par dikhega.',
            PosOrder::KITCHEN_STATUS_READY => 'Order complete — cafe screen par update ho gaya.',
            PosOrder::KITCHEN_STATUS_SERVED => 'Order served — kitchen se hata diya.',
            default => 'Status update ho gaya.',
        };

        return back()->with('success', $message);
    }

    public function serveItem(Request $request, PosOrder $order, PosOrderItem $item): JsonResponse|RedirectResponse
    {
        try {
            $result = $this->kitchen->markItemServed($order, $item);
        } catch (\Throwable $e) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
            }

            return back()->with('error', $e->getMessage());
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'removed' => $result['removed'],
                'item_id' => $item->id,
                'status' => $result['order']->kitchenStatusKey(),
                'status_label' => $result['order']->cafeStatusLabel(),
                'partial' => $result['order']->hasPartialKitchenServed(),
            ]);
        }

        return back()->with('success', 'Item served mark ho gaya.');
    }

    public function position(Request $request, PosOrder $order): JsonResponse
    {
        $data = $request->validate([
            'x' => ['required', 'numeric', 'min:0', 'max:8000'],
            'y' => ['required', 'numeric', 'min:0', 'max:8000'],
        ]);

        try {
            $this->kitchen->saveCardPosition($order, (float) $data['x'], (float) $data['y']);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'group_key' => ['required', 'string', 'max:120'],
            'order_ids' => ['required', 'array', 'min:1'],
            'order_ids.*' => ['integer'],
        ]);

        try {
            $this->kitchen->reorderStack($data['group_key'], $data['order_ids']);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true]);
    }
}
