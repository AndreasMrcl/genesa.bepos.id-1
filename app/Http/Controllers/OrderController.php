<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStockException;
use App\Models\ActivityLog;
use App\Models\Cart;
use App\Models\Discount;
use App\Models\History;
use App\Models\Menu;
use App\Models\Order;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class OrderController extends Controller
{
    public function index()
    {
        $userStore = auth()->user()->store;

        $checkerActive = (bool) optional($userStore->storeConfig)->checker_active;

        $orders = Order::with(['cart.user', 'cart.chair', 'cart.cartMenus.menu'])->get();

        $openBills = Cart::openBills()
            ->where('store_id', $userStore->id)
            ->with(['cartMenus.menu', 'cartMenus.discount'])
            ->latest('opened_at')
            ->get();

        $statuses = [];

        foreach ($orders as $order) {
            try {
                if ($order->status === 'settlement' && in_array($order->payment_type, ['cash', 'edc', 'online'])) {
                    $statuses[$order->no_order] = (object) [
                        'status' => $order->status,
                        'bg_color' => 'text-white text-center bg-green-500 w-fit rounded-xl',
                    ];

                    continue;
                }

                if (! config('midtrans.server_key')) {
                    $statuses[$order->no_order] = (object) [
                        'status' => $order->status ?? 'pending',
                        'bg_color' => 'text-white text-center bg-gray-500 w-fit rounded-xl',
                    ];

                    continue;
                }

                \Midtrans\Config::$serverKey = config('midtrans.server_key');
                \Midtrans\Config::$isProduction = true;

                $status = \Midtrans\Transaction::status($order->no_order);

                $order->update([
                    'status' => $status->transaction_status,
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
                    'bg_color' => $status->transaction_status === 'settlement' ? 'text-white text-center bg-green-500 w-fit rounded-xl' : 'text-white text-center bg-red-500 w-fit rounded-xl',
                ];
            } catch (\Exception $e) {
                $statuses[$order->no_order] = (object) [
                    'status' => 'Error: '.$e->getMessage(),
                    'bg_color' => 'bg-red-500 w-fit text-white text-center rounded-xl',
                ];
            }
        }

        return view('order', compact('orders', 'statuses', 'openBills', 'checkerActive'));
    }

    public function create(Request $request)
    {
        $user = auth()->user();
        $userStore = $user->store;

        $menus = Menu::where('store_id', $userStore->id)->orderBy('name')->get();
        $discounts = Discount::where('store_id', $userStore->id)->get();

        $appendCartId = $request->query('cart_id');
        $appendCart = null;

        if ($appendCartId) {
            $appendCart = Cart::with('cartMenus.menu', 'cartMenus.discount')
                ->where('id', $appendCartId)
                ->where('store_id', $userStore->id)
                ->where('is_open_bill', true)
                ->first();
        }

        if ($appendCart) {
            $cart = $appendCart;
            $mode = 'append';
        } else {
            $cart = $user->carts()
                ->where('is_open_bill', false)
                ->with('cartMenus.menu', 'cartMenus.discount')
                ->latest()
                ->first();

            $hasCommittedOrder = $cart
                ? $cart->orders()->whereNotNull('status')->exists()
                : false;

            if (! $cart || $hasCommittedOrder) {
                $cart = $user->carts()->create([
                    'store_id' => $userStore->id,
                    'total_amount' => 0,
                ]);
                $cart->load('cartMenus.menu', 'cartMenus.discount');
            }
            $mode = 'new';
        }

        return view('ordercreate', compact('menus', 'discounts', 'cart', 'mode'));
    }

    public function checkout(Request $request)
    {
        $user = auth()->user();
        $userStore = $user->store;

        $data = $request->validate([
            'payment_method' => 'required|in:cash,edc,online',
            'cash_received' => 'required_if:payment_method,cash|nullable|integer|min:0',
            'payment_reference' => 'nullable|string|max:255',
            'cart_id' => 'nullable|integer',
        ]);

        $activeShift = $user->settlements()->active()->first();

        if (! $activeShift) {
            return redirect()->route('addorder')->with('error', 'Please open a shift before accepting payment.');
        }

        $cart = null;
        if (! empty($data['cart_id'])) {
            $cart = Cart::with('cartMenus.menu')
                ->where('id', $data['cart_id'])
                ->where('store_id', $userStore->id)
                ->first();
        }

        if (! $cart) {
            $cart = $user->carts()
                ->where('is_open_bill', false)
                ->with('cartMenus.menu')
                ->latest()
                ->first();
        }

        if (! $cart || $cart->cartMenus->isEmpty()) {
            return redirect()->route('addorder')->with('error', 'Keranjang masih kosong.');
        }

        if ($data['payment_method'] === 'cash' && ($data['cash_received'] ?? 0) < $cart->total_amount) {
            $redirectRoute = $cart->is_open_bill ? route('order') : route('addorder');

            return redirect($redirectRoute)->with('error', 'Cash received is less than the total order amount.');
        }

        // Online (Midtrans) → create pending order, generate snap, render Snap UI view
        if ($data['payment_method'] === 'online') {
            if ($cart->is_open_bill) {
                $cart->update(['is_open_bill' => false, 'opened_at' => null]);
            }

            return $this->initOnlinePayment($cart, $userStore);
        }

        // Cash & EDC → settle directly
        try {
            $order = DB::transaction(function () use ($cart, $user, $userStore, $data) {
                // Cleanup any abandoned pending order untuk cart ini (mis. dari online attempt)
                Order::where('cart_id', $cart->id)
                    ->whereNull('status')
                    ->delete();

                $orderNo = 'ORDER-'.strtoupper(substr(Uuid::uuid4()->toString(), 0, 8));

                $order = Order::create([
                    'store_id' => $userStore->id,
                    'cart_id' => $cart->id,
                    'no_order' => $orderNo,
                    'status' => 'settlement',
                    'payment_type' => $data['payment_method'],
                    'payment_reference' => $data['payment_reference'] ?? null,
                ]);

                app(InventoryService::class)->consumeForOrder($order, strict: true);

                if ($cart->is_open_bill) {
                    $cart->update(['is_open_bill' => false, 'opened_at' => null]);
                }

                $user->carts()->create([
                    'store_id' => $userStore->id,
                    'total_amount' => 0,
                ]);

                return $order;
            });

            $this->logActivity(
                'Checkout Order',
                "Checkout order {$order->no_order} via {$order->payment_type} (Total: Rp ".number_format($cart->total_amount, 0, ',', '.').')',
                $userStore->id
            );

            return redirect()->route('order')->with('orderSuccess', [
                'id' => $order->id,
                'no_order' => $order->no_order,
                'total' => $cart->total_amount,
                'payment_method' => $order->payment_type,
                'cash_received' => $data['cash_received'] ?? null,
                'change' => isset($data['cash_received']) ? max(0, $data['cash_received'] - $cart->total_amount) : null,
            ]);
        } catch (InsufficientStockException $e) {
            return redirect()->route('addorder')->with('error', $e->getMessage());
        }
    }

    public function openBill(Request $request)
    {
        $user = auth()->user();
        $userStore = $user->store;

        $data = $request->validate([
            'customer_name' => 'required|string|max:255',
            'cart_id' => 'nullable|integer',
        ]);

        $customerName = trim($data['customer_name']);

        $cart = ! empty($data['cart_id'])
            ? Cart::where('id', $data['cart_id'])->where('store_id', $userStore->id)->first()
            : $user->carts()->where('is_open_bill', false)->latest()->first();

        if (! $cart || $cart->cartMenus()->count() === 0) {
            return redirect()->route('addorder')->with('error', 'Cart is still empty, cannot open a bill.');
        }

        if ($cart->orders()->exists()) {
            return redirect()->route('addorder')->with('error', 'Cart this already has an order, cannot be opened as a bill.');
        }

        DB::transaction(function () use ($cart, $customerName, $user, $userStore) {
            $cart->update([
                'is_open_bill' => true,
                'opened_at' => now(),
                'customer_name' => $customerName,
                'expires_at' => null,
            ]);

            $user->carts()->create([
                'store_id' => $userStore->id,
                'total_amount' => 0,
            ]);
        });

        $this->logActivity(
            'Open Bill',
            "Opening bill for: {$customerName}",
            $userStore->id
        );

        return redirect()->route('order')->with('success', 'Bill for '.$customerName.' successfully opened.');
    }

    public function cancelOpenBill($cartId)
    {
        $user = auth()->user();
        $userStore = $user->store;

        $cart = Cart::where('id', $cartId)
            ->where('store_id', $userStore->id)
            ->where('is_open_bill', true)
            ->first();

        if (! $cart) {
            return redirect()->route('order')->with('error', 'Open bill not found.');
        }

        $cartLabel = $cart->customer_name ?? $cart->chair?->name ?? 'unknown';

        DB::transaction(function () use ($cart) {
            $cart->cartMenus()->delete();
            $cart->orders()->whereNull('status')->delete();
            $cart->delete();
        });

        $this->logActivity(
            'Cancel Open Bill',
            "Canceling open bill for: {$cartLabel}",
            $userStore->id
        );

        return redirect()->route('order')->with('success', 'Open bill canceled.');
    }

    private function initOnlinePayment(Cart $cart, $userStore)
    {
        \Midtrans\Config::$serverKey = config('midtrans.server_key');
        \Midtrans\Config::$isProduction = true;
        \Midtrans\Config::$isSanitized = true;
        \Midtrans\Config::$is3ds = true;

        // Resume existing pending online order for this cart kalau ada (avoid duplicate)
        $order = Order::where('cart_id', $cart->id)
            ->whereNull('status')
            ->where('payment_type', 'online')
            ->first();

        if (! $order) {
            $orderNo = 'ORDER-'.strtoupper(substr(Uuid::uuid4()->toString(), 0, 8));
            $order = Order::create([
                'store_id' => $userStore->id,
                'cart_id' => $cart->id,
                'no_order' => $orderNo,
                'status' => null,
                'payment_type' => 'online',
            ]);
        } else {
            $orderNo = $order->no_order;
        }

        $items = $cart->cartMenus->map(fn ($cm) => [
            'id' => $cm->menu_id,
            'price' => (int) ($cm->subtotal / max($cm->quantity, 1)),
            'quantity' => (int) $cm->quantity,
            'name' => $cm->menu->name.($cm->variety && $cm->variety !== 'normal' ? ' ('.$cm->variety.')' : ''),
        ])->toArray();

        $params = [
            'transaction_details' => [
                'order_id' => $orderNo,
                'gross_amount' => (int) $cart->total_amount,
            ],
            'item_details' => $items,
            'enabled_payments' => ['other_qris'],
        ];

        $snapToken = \Midtrans\Snap::getSnapToken($params);

        return view('orderonline', compact('snapToken', 'order'));
    }

    public function midtransConfirm($orderId)
    {
        $order = Order::find($orderId);

        if (! $order) {
            return redirect()->route('order')->with('error', 'Order not found.');
        }

        $user = auth()->user();
        $userStore = $user->store;

        \Midtrans\Config::$serverKey = config('midtrans.server_key');
        \Midtrans\Config::$isProduction = true;

        try {
            $status = \Midtrans\Transaction::status($order->no_order);
        } catch (\Exception $e) {
            return redirect()->route('order')->with(
                'error',
                'Payment not yet completed at Midtrans. Use the Continue Payment button in the order table to reopen the payment page.'
            );
        }

        if ($status->transaction_status !== 'settlement' && $status->transaction_status !== 'capture') {
            return redirect()->route('order')->with('error', 'Payment not successful. Status: '.$status->transaction_status);
        }

        try {
            DB::transaction(function () use ($order, $status, $user, $userStore) {
                $order->update([
                    'status' => 'settlement',
                    'payment_reference' => $status->transaction_id ?? null,
                ]);

                app(InventoryService::class)->consumeForOrder($order);

                $user->carts()->create([
                    'store_id' => $userStore->id,
                    'total_amount' => 0,
                ]);
            });

            return redirect()->route('order')->with('orderSuccess', [
                'id' => $order->id,
                'no_order' => $order->no_order,
                'total' => $order->cart->total_amount,
                'payment_method' => 'online',
                'cash_received' => null,
                'change' => null,
            ]);
        } catch (\Exception $e) {
            return redirect()->route('order')->with('error', 'Failed to confirm: '.$e->getMessage());
        }
    }

    public function resumeOnline($id)
    {
        $order = Order::with('cart.cartMenus.menu')->find($id);

        if (! $order || $order->payment_type !== 'online' || ! in_array($order->status, [null, 'pending'], true)) {
            return redirect()->route('order')->with('error', 'Invalid order for continuing payment.');
        }

        \Midtrans\Config::$serverKey = config('midtrans.server_key');
        \Midtrans\Config::$isProduction = true;
        \Midtrans\Config::$isSanitized = true;
        \Midtrans\Config::$is3ds = true;

        // Generate fresh order_no untuk avoid Midtrans conflict (order_id sebelumnya mungkin sudah dipakai)
        $newOrderNo = 'ORDER-'.strtoupper(substr(Uuid::uuid4()->toString(), 0, 8));
        $order->update(['no_order' => $newOrderNo]);

        $items = $order->cart->cartMenus->map(fn ($cm) => [
            'id' => $cm->menu_id,
            'price' => (int) ($cm->subtotal / max($cm->quantity, 1)),
            'quantity' => (int) $cm->quantity,
            'name' => $cm->menu->name.($cm->variety && $cm->variety !== 'normal' ? ' ('.$cm->variety.')' : ''),
        ])->toArray();

        $params = [
            'transaction_details' => [
                'order_id' => $newOrderNo,
                'gross_amount' => (int) $order->cart->total_amount,
            ],
            'item_details' => $items,
            'enabled_payments' => ['other_qris'],
        ];

        $snapToken = \Midtrans\Snap::getSnapToken($params);

        return view('orderonline', compact('snapToken', 'order'));
    }

    public function receipt($id)
    {
        $order = Order::with(['cart.cartMenus.menu', 'cart.cartMenus.discount', 'store'])->find($id);

        if (! $order) {
            abort(404);
        }

        return view('receipt', compact('order'));
    }

    public function checker($id)
    {
        $order = Order::with(['cart.cartMenus.menu.station', 'cart.chair', 'store.storeConfig'])->find($id);

        if (! $order) {
            abort(404);
        }

        $config = $order->store->storeConfig;

        if (! $config || ! $config->checker_active) {
            return redirect()->route('order')->with('error', 'Checker feature is not enabled in Store Configuration.');
        }

        $groups = $order->cart->cartMenus
            ->groupBy(function ($cm) {
                $station = $cm->menu->station ?? null;

                return $station && $station->is_active ? $station->name : 'Unassigned';
            })
            ->sortKeys();

        return view('checker', compact('order', 'groups'));
    }

    public function archive($orderId)
    {
        $order = Order::find($orderId);

        $user = auth()->user();
        $userStore = $user->store;

        $settlement = $user->settlements()->active()->first();

        if (! $settlement) {
            return redirect()->back()->with('error', 'Please open a shift before archiving an order.');
        }

        $orderNo = $order->no_order;

        DB::transaction(function () use ($order, $settlement) {
            $history = new History;
            $history->id = $order->id;
            $history->store_id = $settlement->store_id;
            $history->no_order = $order->no_order;
            $history->akun = $order->cart->user->name ?? $order->cart->chair->name ?? '-';
            $history->name = $order->cart->customer_name ?? $order->atas_nama ?? '-';
            $orderDetails = '';

            foreach ($order->cart->cartMenus as $cartMenu) {
                $orderDetails .= $cartMenu->menu->name.' - '.$cartMenu->quantity.' - '.$cartMenu->notes.' - ';
            }

            $history->order = $orderDetails;
            $history->total_amount = $order->cart->total_amount;
            $history->status = $order->status;
            $history->payment_type = $order->payment_type;
            $history->settlement_id = $settlement->id;

            $history->save();

            Cache::forget('history');

            // expected = start_amount + sum cash payments only (drawer accuracy)
            $cashHistoryTotal = $settlement->histories()
                ->where('payment_type', 'cash')
                ->sum('total_amount');
            $settlement->expected = $cashHistoryTotal + $settlement->start_amount;
            $settlement->save();

            foreach ($order->cart->cartMenus as $cartMenu) {
                $cartMenu->delete();
            }

            $order->cart->delete();

            $order->delete();
        });

        $this->logActivity(
            'Archive Order',
            "Archiving order: {$orderNo}",
            $userStore->id
        );

        return redirect()->back()->with('success', 'Order archived successfully');
    }

    public function destroy($id)
    {
        $order = Order::find($id);

        if (! $order) {
            return redirect(route('order'))->with('error', 'Order tidak ditemukan.');
        }

        $userStore = auth()->user()->store;
        $orderNo = $order->no_order;

        DB::transaction(function () use ($order) {
            app(InventoryService::class)->restoreForOrder($order);
            $order->delete();
        });

        $this->logActivity(
            'Delete Order',
            "Deleting order: {$orderNo}",
            $userStore->id
        );

        return redirect(route('order'))->with('success', 'Order Berhasil Dihapus !');
    }

    private function logActivity($type, $description, $storeId)
    {
        ActivityLog::create([
            'user_id'       => Auth::id(),
                    'staff_id'      => Auth::id(),
            'store_id'      => $storeId,
            'activity_type' => $type,
            'description'   => $description,
            'created_at'    => now(),
        ]);

        Cache::forget("activities_{$storeId}");
    }
}