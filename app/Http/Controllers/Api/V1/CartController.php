<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\CartResource;
use App\Models\Cart;
use App\Models\CartMenu;
use App\Models\Discount;
use App\Models\Menu;
use App\Services\InventoryService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    use ApiResponse;

    public function show(Request $request)
    {
        $cart = $this->resolveCart($request);

        return $this->ok(['cart' => new CartResource($cart->load('cartMenus.menu', 'cartMenus.discount', 'chair'))]);
    }

    public function addItem(Request $request)
    {
        $data = $request->validate([
            'menu_id'     => 'required|exists:menus,id',
            'quantity'    => 'required|integer|min:1',
            'variety'     => 'nullable|string|max:50',
            'notes'       => 'nullable|string|max:255',
            'discount_id' => 'nullable|exists:discounts,id',
            'cart_id'     => 'nullable|integer',
        ]);

        $user = $request->user();
        $storeId = $user->store->id;

        $cart = $this->resolveCart($request);

        if ($locked = $this->lockedResponse($cart)) {
            return $locked;
        }

        $menu = Menu::findOrFail($data['menu_id']);
        $quantity = (int) $data['quantity'];

        $variety = $menu->has_variety
            ? (! empty($data['variety']) && in_array($data['variety'], $menu->varieties ?? [], true)
                ? $data['variety']
                : ($menu->varieties[0] ?? 'normal'))
            : 'normal';

        $insufficient = app(InventoryService::class)->canFulfillCart($cart, $menu, $quantity, $variety);

        if (! empty($insufficient)) {
            return $this->error('stock', 'Stok bahan tidak cukup: '.implode(', ', $insufficient), 422);
        }

        $subtotal = $menu->price * $quantity;

        $discount = ! empty($data['discount_id'])
            ? Discount::find($data['discount_id'])
            : null;

        if ($discount) {
            $subtotal -= $subtotal * ($discount->percentage / 100);
        }

        // A2: bulatkan ke rupiah utuh (Rupiah tak punya sen) supaya nilai tersimpan = nilai ditagih.
        $subtotal = (int) round($subtotal);

        $existing = CartMenu::where([
            'cart_id'     => $cart->id,
            'menu_id'     => $menu->id,
            'variety'     => $variety,
            'notes'       => $data['notes'] ?? null,
            'discount_id' => $discount?->id,
        ])->first();

        if ($existing) {
            $existing->increment('quantity', $quantity);
            $existing->increment('subtotal', $subtotal);
        } else {
            CartMenu::create([
                'store_id'    => $storeId,
                'cart_id'     => $cart->id,
                'menu_id'     => $menu->id,
                'quantity'    => $quantity,
                'variety'     => $variety,
                'notes'       => $data['notes'] ?? null,
                'subtotal'    => $subtotal,
                'discount_id' => $discount?->id,
            ]);
        }

        $cart->increment('total_amount', $subtotal);

        return $this->ok([
            'cart' => new CartResource($cart->fresh()->load('cartMenus.menu', 'cartMenus.discount', 'chair')),
        ]);
    }

    public function updateItem(Request $request, int $id)
    {
        $data = $request->validate([
            'quantity'    => 'nullable|integer|min:1',
            'notes'       => 'nullable|string|max:255',
            'variety'     => 'nullable|string|max:50',
            'discount_id' => 'nullable|exists:discounts,id',
        ]);

        $user = $request->user();
        $storeId = $user->store->id;

        $cartMenu = CartMenu::where('id', $id)->where('store_id', $storeId)->firstOrFail();
        $cart = $cartMenu->cart;

        if ($locked = $this->lockedResponse($cart)) {
            return $locked;
        }

        $menu = $cartMenu->menu;

        $quantity = (int) ($data['quantity'] ?? $cartMenu->quantity);
        $variety = $data['variety'] ?? $cartMenu->variety;
        $notes = array_key_exists('notes', $data) ? $data['notes'] : $cartMenu->notes;

        $discount = array_key_exists('discount_id', $data)
            ? ($data['discount_id'] ? Discount::find($data['discount_id']) : null)
            : $cartMenu->discount;

        $delta = max(0, $quantity - $cartMenu->quantity);
        if ($delta > 0) {
            $insufficient = app(InventoryService::class)->canFulfillCart($cart, $menu, $delta, $variety);
            if (! empty($insufficient)) {
                return $this->error('stock', 'Stok bahan tidak cukup: '.implode(', ', $insufficient), 422);
            }
        }

        $newSubtotal = $menu->price * $quantity;
        if ($discount) {
            $newSubtotal -= $newSubtotal * ($discount->percentage / 100);
        }
        // A2: bulatkan ke rupiah utuh.
        $newSubtotal = (int) round($newSubtotal);

        DB::transaction(function () use ($cart, $cartMenu, $quantity, $variety, $notes, $discount, $newSubtotal) {
            $delta = $newSubtotal - $cartMenu->subtotal;
            $cartMenu->update([
                'quantity'    => $quantity,
                'variety'     => $variety,
                'notes'       => $notes,
                'discount_id' => $discount?->id,
                'subtotal'    => $newSubtotal,
            ]);
            $cart->increment('total_amount', $delta);
        });

        return $this->ok([
            'cart' => new CartResource($cart->fresh()->load('cartMenus.menu', 'cartMenus.discount', 'chair')),
        ]);
    }

    public function deleteItem(Request $request, int $id)
    {
        $storeId = $request->user()->store->id;

        $cartMenu = CartMenu::where('id', $id)->where('store_id', $storeId)->firstOrFail();
        $cart = $cartMenu->cart;

        if ($locked = $this->lockedResponse($cart)) {
            return $locked;
        }

        DB::transaction(function () use ($cartMenu, $cart) {
            $subtotal = $cartMenu->subtotal;
            $cartMenu->delete();
            $cart->decrement('total_amount', $subtotal);
        });

        return $this->ok([
            'cart' => new CartResource($cart->fresh()->load('cartMenus.menu', 'cartMenus.discount', 'chair')),
        ]);
    }

    public function reset(Request $request)
    {
        $cart = $this->resolveCart($request);

        if ($locked = $this->lockedResponse($cart)) {
            return $locked;
        }

        DB::transaction(function () use ($cart) {
            $cart->cartMenus()->delete();
            $cart->update(['total_amount' => 0]);
        });

        return $this->ok([
            'cart' => new CartResource($cart->fresh()->load('cartMenus.menu', 'cartMenus.discount', 'chair')),
        ]);
    }

    /**
     * Tolak mutasi kalau cart punya pending online order.
     * User harus selesaikan / batalkan pembayaran dulu.
     */
    private function lockedResponse(Cart $cart)
    {
        if ($cart->pendingOnlineOrder()) {
            return $this->error(
                'payment_pending',
                'Ada pembayaran online yang belum selesai untuk keranjang ini. Selesaikan atau batalkan pembayaran dulu.',
                409
            );
        }

        return null;
    }

    /**
     * Resolve cart: pakai cart_id (open-bill), atau draft cart aktif user (auto-create kalau perlu).
     */
    private function resolveCart(Request $request): Cart
    {
        $user = $request->user();
        $storeId = $user->store->id;
        $cartId = $request->query('cart_id') ?? $request->input('cart_id');

        if ($cartId) {
            $cart = Cart::with('cartMenus.menu', 'cartMenus.discount', 'chair')
                ->where('id', $cartId)
                ->where('store_id', $storeId)
                ->where('is_open_bill', true)
                ->firstOrFail();

            return $cart;
        }

        $cart = $user->carts()
            ->where('is_open_bill', false)
            ->with('cartMenus.menu', 'cartMenus.discount')
            ->latest()
            ->first();

        $hasCommittedOrder = $cart
            ? $cart->orders()->whereNotNull('status')->exists()
            : false;

        if (! $cart || $hasCommittedOrder) {
            $cart = $this->createDraftCartSafely($user, $storeId);
        }

        return $cart;
    }

    /**
     * A3: buat draft cart baru dengan lock agar request paralel (double-tap / retry)
     * tidak menghasilkan cart ganda. Re-check di dalam lock; kalau lock timeout,
     * fallback ke pembuatan biasa supaya user tidak pernah terblokir.
     */
    private function createDraftCartSafely($user, int $storeId): Cart
    {
        $make = function () use ($user, $storeId): Cart {
            $cart = $user->carts()->where('is_open_bill', false)->latest()->first();
            $hasCommitted = $cart ? $cart->orders()->whereNotNull('status')->exists() : false;

            if (! $cart || $hasCommitted) {
                $cart = $user->carts()->create([
                    'store_id'     => $storeId,
                    'total_amount' => 0,
                ]);
            }

            return $cart;
        };

        try {
            $cart = Cache::lock("resolve-cart-user-{$user->id}", 10)->block(5, $make);
        } catch (LockTimeoutException $e) {
            $cart = $make();
        }

        return $cart->load('cartMenus.menu', 'cartMenus.discount', 'chair');
    }
}
