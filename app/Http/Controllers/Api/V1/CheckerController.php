<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class CheckerController extends Controller
{
    use ApiResponse;

    public function show(Request $request, int $id)
    {
        $storeId = $request->user()->store->id;

        $order = Order::with([
            'cart.cartMenus.menu.station',
            'cart.chair',
            'store.storeConfig',
        ])
            ->where('id', $id)
            ->where('store_id', $storeId)
            ->first();

        if (! $order) {
            return $this->error('order', 'Order tidak ditemukan.', 404);
        }

        $config = $order->store->storeConfig;

        if (! $config || ! $config->checker_active) {
            return $this->error('checker', 'Checker feature is not enabled.', 422);
        }

        $tickets = $order->cart->cartMenus
            ->groupBy(function ($cm) {
                $station = $cm->menu->station ?? null;

                return $station && $station->is_active ? $station->name : 'Unassigned';
            })
            ->sortKeys()
            ->map(fn ($items, $stationName) => [
                'station' => $stationName,
                'items'   => $items->map(fn ($cm) => [
                    'name'     => $cm->menu->name,
                    'variety'  => $cm->variety,
                    'notes'    => $cm->notes,
                    'quantity' => (int) $cm->quantity,
                ])->values(),
            ])
            ->values();

        return $this->ok([
            'order' => [
                'no_order' => $order->no_order,
                'datetime' => optional($order->created_at)->toIso8601String(),
                'layanan'  => $order->layanan ?? 'dine-in',
                'table'    => $order->cart->customer_name ?? $order->cart?->chair?->name,
            ],
            'tickets' => $tickets,
        ]);
    }
}
