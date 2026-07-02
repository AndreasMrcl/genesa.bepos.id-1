<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\Order;
use App\Models\Showcase;
use App\Services\InventoryService;
use Illuminate\Support\Facades\Cache;

class PagesController extends Controller
{
    public function home()
    {
        $chair = auth()->user();
        $storeId = $chair->store_id;

        $showcase = Cache::remember("showcase_{$storeId}", now()->addMinutes(60), function () use ($storeId) {
            return Showcase::where('store_id', $storeId)->select('id', 'img')->get();
        });

        $profil = collect([$chair->store]);

        $menus = Cache::remember("menus_{$storeId}", now()->addMinutes(60), function () use ($storeId) {
            return Menu::where('store_id', $storeId)->select('id', 'name', 'price', 'img')->paginate(10);
        });

        $pendingCart = null;
        
        if (! session('cart_acknowledged', false)) {
            $sessionStart = session('session_started_at');

            $query = $chair->carts()
                ->with('cartMenus.menu')
                ->where('is_open_bill', false)
                ->whereDoesntHave('orders')
                ->whereHas('cartMenus')
                ->where('expires_at', '>', now());

            if ($sessionStart) {
                $query->where('created_at', '<', $sessionStart);
            }

            $pendingCart = $query->latest()->first();
        }

        return view('user.home', compact('profil', 'menus', 'showcase', 'pendingCart'));
    }

    public function antrian()
    {
        $chair = auth()->user();

        // A5: scope ke store milik chair (hindari bocor lintas-store) & urutkan terbaru.
        $orders = Order::with(['cart.user', 'cart.chair', 'cart.cartMenus.menu'])
            ->where('store_id', $chair->store_id)
            ->latest()
            ->get();

        $statuses = [];

        // A5: status terminal tidak perlu di-poll ulang ke Midtrans (tak akan berubah lagi) —
        // hemat panggilan jaringan & hindari re-consume/re-delete di request GET.
        $terminal = ['settlement', 'capture', 'expire', 'deny', 'cancel'];

        foreach ($orders as $order) {
            try {
                if ($order->status === 'settlement' && $order->payment_type === 'cash') {
                    $statuses[$order->no_order] = (object) [
                        'status' => $order->status,
                        'bg_color' => 'text-white text-center bg-green-500 w-fit rounded-xl'
                    ];
                    continue; // Skip further processing for this order
                }

                if (in_array($order->status, $terminal, true)) {
                    $isGreen = in_array($order->status, ['settlement', 'capture'], true);
                    $statuses[$order->no_order] = (object) [
                        'status' => $order->status,
                        'bg_color' => ($isGreen ? 'bg-green-500' : 'bg-red-500').' text-white text-center w-fit rounded-xl',
                    ];
                    continue;
                }

                if (! config('midtrans.server_key')) {
                    $statuses[$order->no_order] = (object) [
                        'status' => $order->status ?? 'pending',
                        'bg_color' => 'text-white text-center bg-gray-500 w-fit rounded-xl'
                    ];
                    continue;
                }

                \Midtrans\Config::$serverKey = config('midtrans.server_key');
                \Midtrans\Config::$isProduction = true;

                $status = \Midtrans\Transaction::status($order->no_order);

                $order->update([
                    'status' => $status->transaction_status,
                    'payment_type' => $status->payment_type ?? null,
                ]);

                if ($status->transaction_status === 'settlement') {
                    app(InventoryService::class)->consumeForOrder($order);
                }

                if ($status->transaction_status === 'expire') {
                    $order->delete();
                    continue;
                }

                $statuses[$order->no_order] = (object) [
                    'status' => $status->transaction_status,
                    'bg_color' => $status->transaction_status === 'settlement' ? 'text-white text-center bg-green-500 w-fit rounded-xl' : 'text-white text-center bg-red-500 w-fit rounded-xl'
                ];
            } catch (\Exception $e) {
                // Midtrans returns 404 "Transaction doesn't exist" when the user
                // closed the Snap popup before picking a payment method. The order
                // exists locally but Midtrans has no record of it — treat the same
                // as 'expire' once a short grace period has passed (in case the
                // user is still in checkout).
                $isNotFound = str_contains($e->getMessage(), "Transaction doesn't exist")
                    || str_contains($e->getMessage(), 'HTTP status code: 404');

                if ($isNotFound && $order->created_at->lt(now()->subMinutes(10))) {
                    $order->delete();
                    continue;
                }

                if ($isNotFound) {
                    $statuses[$order->no_order] = (object) [
                        'status' => 'pending',
                        'bg_color' => 'text-white text-center bg-amber-500 w-fit rounded-xl',
                    ];
                    continue;
                }

                $statuses[$order->no_order] = (object) [
                    'status' => 'Error: ' . $e->getMessage(),
                    'bg_color' => 'bg-red-500 w-fit text-white text-center rounded-xl'
                ];
            }
        }

        return view('user.antrian', compact('orders', 'statuses'));
    }

    public function akun()
    {
        $user = auth()->user();

        return view('user.akun', compact('user'));
    }
}