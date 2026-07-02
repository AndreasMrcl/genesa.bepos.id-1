<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Api\V1\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Cart;
use App\Models\History;
use App\Models\Order;
use App\Services\ActivityLogger;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class OrderController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $storeId = $request->user()->store->id;

        $orders = Order::with(['cart.user', 'cart.chair', 'cart.cartMenus.menu', 'cart.cartMenus.discount'])
            ->where('store_id', $storeId)
            ->latest()
            ->get();

        return $this->ok(['orders' => OrderResource::collection($orders)]);
    }

    public function show(Request $request, int $id)
    {
        $storeId = $request->user()->store->id;

        $order = Order::with(['cart.user', 'cart.chair', 'cart.cartMenus.menu', 'cart.cartMenus.discount'])
            ->where('store_id', $storeId)
            ->where('id', $id)
            ->firstOrFail();

        return $this->ok(['order' => new OrderResource($order)]);
    }

    /**
     * Sync status pending online orders dari Midtrans. Dipanggil saat pull-to-refresh.
     */
    public function syncStatus(Request $request)
    {
        $storeId = $request->user()->store->id;

        $orders = Order::where('store_id', $storeId)
            ->where('payment_type', 'online')
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhereIn('status', ['pending']);
            })
            ->get();

        if (! config('midtrans.server_key')) {
            return $this->ok(['synced' => 0]);
        }

        \Midtrans\Config::$serverKey = config('midtrans.server_key');
        \Midtrans\Config::$isProduction = true;

        $synced = 0;
        foreach ($orders as $order) {
            try {
                $status = \Midtrans\Transaction::status($order->no_order);

                if ($status->transaction_status === 'expire') {
                    $order->delete();
                    $synced++;

                    continue;
                }

                $order->update(['status' => $status->transaction_status]);

                if ($status->transaction_status === 'settlement') {
                    app(InventoryService::class)->consumeForOrder($order);
                }

                $synced++;
            } catch (\Exception $e) {
                // skip — keep order, klien retry kemudian
            }
        }

        return $this->ok(['synced' => $synced]);
    }

    public function checkout(Request $request)
    {
        $user = $request->user();
        $userStore = $user->store;

        $data = $request->validate([
            'payment_method'    => 'required|in:cash,edc,online',
            'cash_received'     => 'required_if:payment_method,cash|nullable|integer|min:0',
            'payment_reference' => 'nullable|string|max:255',
            'cart_id'           => 'nullable|integer',
        ]);

        $activeShift = $user->settlements()->active()->first();
        if (! $activeShift) {
            return $this->error('shift', 'Belum ada shift aktif. Buka shift dulu.', 409);
        }

        $cart = null;
        if (! empty($data['cart_id'])) {
            // A4: cart_id hanya untuk checkout open-bill (samakan dgn resolveCart).
            $cart = Cart::with('cartMenus.menu')
                ->where('id', $data['cart_id'])
                ->where('store_id', $userStore->id)
                ->where('is_open_bill', true)
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
            return $this->error('cart', 'Keranjang masih kosong.', 422);
        }

        // Cart yang sudah punya order committed (status != null) tidak boleh di-checkout lagi.
        // Mencegah double order + double consume kalau cart di-settle via webhook (yang tidak
        // merotasi cart) lalu checkout dipanggil di atas state basi. Samakan dgn resolveCart.
        if ($cart->orders()->whereNotNull('status')->exists()) {
            return $this->error('cart', 'Keranjang ini sudah punya order. Muat ulang keranjang.', 409);
        }

        // kalau sudah ada pembayaran online yang belum selesai, cash/edc
        // tidak boleh menghapusnya diam-diam (snap masih bisa dibayar). Online tetap boleh
        // karena me-reuse pending order yang sama & total cart terjamin cocok (cart terkunci).
        if ($data['payment_method'] !== 'online' && $cart->pendingOnlineOrder()) {
            return $this->error(
                'payment_pending',
                'Keranjang ini punya pembayaran online yang belum selesai. Lanjutkan atau batalkan pembayaran online dulu.',
                409
            );
        }

        if ($data['payment_method'] === 'cash' && ($data['cash_received'] ?? 0) < $cart->total_amount) {
            return $this->error('cash_received', 'Uang yang diterima kurang dari total.', 422);
        }

        // Online → return snap_token, mobile gunakan SDK Midtrans Flutter
        if ($data['payment_method'] === 'online') {
            if ($cart->is_open_bill) {
                $cart->update(['is_open_bill' => false, 'opened_at' => null]);
            }

            return $this->initOnlinePayment($cart, $userStore);
        }

        // Cash & EDC → settle langsung
        try {
            $order = DB::transaction(function () use ($cart, $user, $userStore, $data) {
                Order::where('cart_id', $cart->id)->whereNull('status')->delete();

                $orderNo = 'ORDER-'.strtoupper(substr(Uuid::uuid4()->toString(), 0, 8));

                $order = Order::create([
                    'store_id'          => $userStore->id,
                    'cart_id'           => $cart->id,
                    'no_order'          => $orderNo,
                    'status'            => 'settlement',
                    'payment_type'      => $data['payment_method'],
                    'payment_reference' => $data['payment_reference'] ?? null,
                ]);

                app(InventoryService::class)->consumeForOrder($order, strict: true);

                if ($cart->is_open_bill) {
                    $cart->update(['is_open_bill' => false, 'opened_at' => null]);
                }

                $user->carts()->create([
                    'store_id'     => $userStore->id,
                    'total_amount' => 0,
                ]);

                return $order;
            });
        } catch (InsufficientStockException $e) {
            return $this->error('stock', $e->getMessage(), 422);
        }

        ActivityLogger::log(
            'Checkout Order',
            "Checkout order {$order->no_order} via {$order->payment_type} (Total: Rp ".number_format($cart->total_amount, 0, ',', '.').')',
            $userStore->id
        );

        $order->load('cart.cartMenus.menu', 'cart.cartMenus.discount', 'cart.chair');

        return $this->ok([
            'order' => new OrderResource($order),
            'change' => isset($data['cash_received']) ? max(0, $data['cash_received'] - $cart->total_amount) : null,
        ]);
    }

    public function confirmOnline(Request $request, int $id)
    {
        $storeId = $request->user()->store->id;

        $order = Order::where('id', $id)->where('store_id', $storeId)->first();
        if (! $order) {
            return $this->error('order', 'Order tidak ditemukan.', 404);
        }

        \Midtrans\Config::$serverKey = config('midtrans.server_key');
        \Midtrans\Config::$isProduction = true;

        try {
            $status = \Midtrans\Transaction::status($order->no_order);
        } catch (\Exception $e) {
            return $this->error('midtrans', 'Pembayaran belum selesai di Midtrans.', 409);
        }

        if (! in_array($status->transaction_status, ['settlement', 'capture'])) {
            return $this->error('midtrans', 'Pembayaran belum sukses. Status: '.$status->transaction_status, 409);
        }

        DB::transaction(function () use ($order, $status, $request) {
            $order->update([
                'status'            => 'settlement',
                'payment_reference' => $status->transaction_id ?? null,
            ]);

            app(InventoryService::class)->consumeForOrder($order);

            $request->user()->carts()->create([
                'store_id'     => $order->store_id,
                'total_amount' => 0,
            ]);
        });

        ActivityLogger::log(
            'Confirm Online Order',
            "Confirming online order {$order->no_order}",
            $order->store_id
        );

        $order->load('cart.cartMenus.menu', 'cart.cartMenus.discount', 'cart.chair');

        return $this->ok(['order' => new OrderResource($order)]);
    }

    /**
     * Batalkan pending online order → membuka kembali cart.
     * Rekonsiliasi dulu ke Midtrans: kalau ternyata sudah dibayar, settle (jangan hapus);
     * kalau belum, expire snap-nya supaya tidak bisa dibayar lagi, lalu hapus order.
     */
    public function cancelOnline(Request $request, int $id)
    {
        $user = $request->user();
        $storeId = $user->store->id;

        $order = Order::where('id', $id)
            ->where('store_id', $storeId)
            ->where('payment_type', 'online')
            ->first();

        if (! $order || ! in_array($order->status, [null, 'pending'], true)) {
            return $this->error('order', 'Tidak ada pembayaran online yang bisa dibatalkan.', 422);
        }

        if (config('midtrans.server_key')) {
            \Midtrans\Config::$serverKey = config('midtrans.server_key');
            \Midtrans\Config::$isProduction = true;

            try {
                $status = \Midtrans\Transaction::status($order->no_order);

                // Sudah dibayar → jangan hapus, selesaikan saja (hindari lost payment).
                if (in_array($status->transaction_status, ['settlement', 'capture'], true)) {
                    DB::transaction(function () use ($order, $status, $user, $storeId) {
                        $order->update([
                            'status'            => 'settlement',
                            'payment_reference' => $status->transaction_id ?? null,
                        ]);

                        app(InventoryService::class)->consumeForOrder($order);

                        $user->carts()->create([
                            'store_id'     => $storeId,
                            'total_amount' => 0,
                        ]);
                    });

                    ActivityLogger::log(
                        'Confirm Online Order',
                        "Cancel diminta tapi order {$order->no_order} sudah dibayar — diselesaikan",
                        $storeId
                    );

                    $order->load('cart.cartMenus.menu', 'cart.cartMenus.discount', 'cart.chair');

                    return $this->ok(['settled' => true, 'order' => new OrderResource($order)]);
                }

                // Status 'pending' → snap MASIH bisa dibayar. Wajib expire dulu; kalau gagal,
                // JANGAN hapus order (mencegah lost payment kalau customer terlanjur bayar) —
                // minta user coba lagi.
                if ($status->transaction_status === 'pending') {
                    try {
                        \Midtrans\Transaction::expire($order->no_order);
                    } catch (\Exception $e) {
                        return $this->error('midtrans', 'Gagal membatalkan pembayaran di Midtrans. Coba lagi.', 409);
                    }
                }
                // Status terminal lain (expire/deny/cancel/failure) → tak ada snap payable, aman dihapus.
            } catch (\Exception $e) {
                // status() 404 / transaksi tak ada di Midtrans → aman dihapus lokal
            }
        }

        $orderNo = $order->no_order;
        $order->delete();

        ActivityLogger::log('Cancel Online Payment', "Canceling pending online order {$orderNo}", $storeId);

        return $this->ok(['settled' => false, 'message' => 'Pembayaran online dibatalkan.']);
    }

    public function resumeOnline(Request $request, int $id)
    {
        $storeId = $request->user()->store->id;

        $order = Order::with('cart.cartMenus.menu')
            ->where('id', $id)
            ->where('store_id', $storeId)
            ->first();

        if (! $order || $order->payment_type !== 'online' || ! in_array($order->status, [null, 'pending'], true)) {
            return $this->error('order', 'Order tidak valid untuk melanjutkan pembayaran.', 422);
        }

        \Midtrans\Config::$serverKey = config('midtrans.server_key');
        \Midtrans\Config::$isProduction = true;
        \Midtrans\Config::$isSanitized = true;
        \Midtrans\Config::$is3ds = true;

        $oldNoOrder = $order->no_order;

        // A1: rekonsiliasi snap lama sebelum bikin yang baru — cegah dua snap payable
        // untuk order yang sama (lost / double payment).
        if (config('midtrans.server_key') && $oldNoOrder) {
            try {
                $status = \Midtrans\Transaction::status($oldNoOrder);

                // Ternyata sudah dibayar → selesaikan, jangan bikin snap baru.
                if (in_array($status->transaction_status, ['settlement', 'capture'], true)) {
                    DB::transaction(function () use ($order, $status, $user, $storeId) {
                        $order->update([
                            'status'            => 'settlement',
                            'payment_reference' => $status->transaction_id ?? null,
                        ]);

                        app(InventoryService::class)->consumeForOrder($order);

                        $user->carts()->create([
                            'store_id'     => $storeId,
                            'total_amount' => 0,
                        ]);
                    });

                    $order->load('cart.cartMenus.menu', 'cart.cartMenus.discount', 'cart.chair');

                    return $this->ok(['settled' => true, 'order' => new OrderResource($order)]);
                }

                // Masih pending → snap lama masih bisa dibayar. Expire dulu; kalau gagal
                // JANGAN lanjut bikin snap baru (hindari dua snap aktif).
                if ($status->transaction_status === 'pending') {
                    try {
                        \Midtrans\Transaction::expire($oldNoOrder);
                    } catch (\Exception $e) {
                        return $this->error('midtrans', 'Gagal menutup pembayaran lama. Coba lagi.', 409);
                    }
                }
            } catch (\Exception $e) {
                // status() 404 / transaksi lama tak ada di Midtrans → aman lanjut
            }
        }

        $newOrderNo = 'ORDER-'.strtoupper(substr(Uuid::uuid4()->toString(), 0, 8));
        $order->update(['no_order' => $newOrderNo]);

        $items = $order->cart->cartMenus->map(fn ($cm) => [
            'id'       => $cm->menu_id,
            'price'    => (int) ($cm->subtotal / max($cm->quantity, 1)),
            'quantity' => (int) $cm->quantity,
            'name'     => $cm->menu->name.($cm->variety && $cm->variety !== 'normal' ? ' ('.$cm->variety.')' : ''),
        ])->toArray();

        $params = [
            'transaction_details' => [
                'order_id'     => $newOrderNo,
                'gross_amount' => (int) $order->cart->total_amount,
            ],
            'item_details'     => $items,
            'enabled_payments' => ['other_qris'],
        ];

        $snapToken = \Midtrans\Snap::getSnapToken($params);

        return $this->ok([
            'settled'    => false,
            'snap_token' => $snapToken,
            'order'      => [
                'id'           => $order->id,
                'no_order'     => $newOrderNo,
                'total_amount' => (int) $order->cart->total_amount,
            ],
        ]);
    }

    public function archive(Request $request, int $id)
    {
        $user = $request->user();
        $userStore = $user->store;

        $settlement = $user->settlements()->active()->first();
        if (! $settlement) {
            return $this->error('shift', 'Buka shift dulu sebelum archive.', 409);
        }

        // A7: lock baris order supaya archive paralel (double-tap / 2 device) tidak
        // dobel bikin History (id History = id order). Kalau order sudah tak ada, idempoten.
        $orderNo = DB::transaction(function () use ($id, $userStore, $settlement) {
            $order = Order::with('cart.cartMenus.menu', 'cart.user', 'cart.chair')
                ->where('id', $id)
                ->where('store_id', $userStore->id)
                ->lockForUpdate()
                ->first();

            if (! $order) {
                return null;
            }

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

            $cashHistoryTotal = $settlement->histories()->where('payment_type', 'cash')->sum('total_amount');
            $settlement->expected = $cashHistoryTotal + $settlement->start_amount;
            $settlement->save();

            Cache::forget("settlement_detail_{$settlement->id}");

            foreach ($order->cart->cartMenus as $cartMenu) {
                $cartMenu->delete();
            }

            $order->cart->delete();
            $order->delete();

            return $order->no_order;
        });

        // Order sudah tak ada: bedakan "sudah diarsip" (idempoten) vs "tidak pernah ada" (404).
        if ($orderNo === null) {
            if (History::whereKey($id)->where('store_id', $userStore->id)->exists()) {
                return $this->ok(['message' => 'Order sudah diarsipkan.']);
            }

            return $this->error('order', 'Order tidak ditemukan.', 404);
        }

        ActivityLogger::log('Archive Order', "Archiving order: {$orderNo}", $userStore->id);

        return $this->ok(['message' => 'Order archived successfully']);
    }

    public function destroy(Request $request, int $id)
    {
        $storeId = $request->user()->store->id;

        $order = Order::where('id', $id)->where('store_id', $storeId)->first();
        if (! $order) {
            return $this->error('order', 'Order tidak ditemukan.', 404);
        }

        $orderNo = $order->no_order;

        DB::transaction(function () use ($order) {
            app(InventoryService::class)->restoreForOrder($order);
            $order->delete();
        });

        ActivityLogger::log('Delete Order', "Deleting order: {$orderNo}", $storeId);

        return $this->ok(['message' => 'Order deleted.']);
    }

    private function initOnlinePayment(Cart $cart, $userStore)
    {
        \Midtrans\Config::$serverKey = config('midtrans.server_key');
        \Midtrans\Config::$isProduction = true;
        \Midtrans\Config::$isSanitized = true;
        \Midtrans\Config::$is3ds = true;

        $order = Order::where('cart_id', $cart->id)
            ->whereNull('status')
            ->where('payment_type', 'online')
            ->first();

        if (! $order) {
            $orderNo = 'ORDER-'.strtoupper(substr(Uuid::uuid4()->toString(), 0, 8));
            $order = Order::create([
                'store_id'     => $userStore->id,
                'cart_id'      => $cart->id,
                'no_order'     => $orderNo,
                'status'       => null,
                'payment_type' => 'online',
            ]);
        } else {
            $orderNo = $order->no_order;
        }

        $items = $cart->cartMenus->map(fn ($cm) => [
            'id'       => $cm->menu_id,
            'price'    => (int) ($cm->subtotal / max($cm->quantity, 1)),
            'quantity' => (int) $cm->quantity,
            'name'     => $cm->menu->name.($cm->variety && $cm->variety !== 'normal' ? ' ('.$cm->variety.')' : ''),
        ])->toArray();

        $params = [
            'transaction_details' => [
                'order_id'     => $orderNo,
                'gross_amount' => (int) $cart->total_amount,
            ],
            'item_details'     => $items,
            'enabled_payments' => ['other_qris'],
        ];

        $snapToken = \Midtrans\Snap::getSnapToken($params);

        return $this->ok([
            'snap_token' => $snapToken,
            'order'      => [
                'id'           => $order->id,
                'no_order'     => $orderNo,
                'total_amount' => (int) $cart->total_amount,
            ],
        ]);
    }
}