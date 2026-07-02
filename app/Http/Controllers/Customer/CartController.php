<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartMenu;
use App\Models\Discount;
use App\Models\Menu;
use App\Services\InventoryService;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function cart()
    {
        $chair = auth()->user();
        $cart = Cart::getActiveOrCreateForChair($chair);

        return view('user.cart', compact('cart'));
    }

    public function postcart(Request $request)
    {
        $request->validate([
            'menu_id' => 'required|exists:menus,id',
            'quantity' => 'required|integer|min:1',
            'variety' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            'discount_id' => 'nullable|exists:discounts,id',
        ]);

        $chair = auth()->user();
        $storeId = $chair->store_id;

        $cart = Cart::getActiveOrCreateForChair($chair);

        $menu = Menu::findOrFail($request->input('menu_id'));
        $quantity = (int) $request->input('quantity');

        $variety = $menu->has_variety
            ? ($request->variety && in_array($request->variety, $menu->varieties ?? [], true)
                ? $request->variety
                : ($menu->varieties[0] ?? 'normal'))
            : 'normal';

        $insufficient = app(InventoryService::class)->canFulfillCart($cart, $menu, $quantity, $variety);

        if (! empty($insufficient)) {
            return redirect()->back()->with('error', 'Stok bahan tidak cukup: '.implode(', ', $insufficient));
        }

        $subtotal = (float) $menu->price * $quantity;

        $discount = null;

        if ($request->filled('discount_id')) {
            $discount = Discount::find($request->input('discount_id'));
            if ($discount) {
                $discountAmount = $subtotal * ($discount->percentage / 100);
                $subtotal -= $discountAmount;
            }
        }

        // A2: bulatkan ke rupiah utuh (Rupiah tak punya sen).
        $subtotal = (int) round($subtotal);

        $existingCartMenu = CartMenu::where('cart_id', $cart->id)
            ->where('menu_id', $menu->id)
            ->where('variety', $variety)
            ->where('notes', $request->input('notes'))
            ->where('discount_id', $discount ? $discount->id : null)
            ->first();

        if ($existingCartMenu) {
            $existingCartMenu->quantity += $quantity;
            $existingCartMenu->subtotal += $subtotal;
            $existingCartMenu->save();
        } else {
            CartMenu::create([
                'store_id' => $storeId,
                'cart_id' => $cart->id,
                'menu_id' => $menu->id,
                'quantity' => $quantity,
                'variety' => $variety,
                'notes' => $request->input('notes'),
                'subtotal' => $subtotal,
                'discount_id' => $discount ? $discount->id : null,
            ]);
        }

        $cart->update([
            'total_amount' => $cart->total_amount + $subtotal,
            'expires_at' => now()->addMinutes(Cart::EXPIRATION_MINUTES),
        ]);

        return redirect(route('user-product'));
    }

    public function removecart($id)
    {
        $chair = auth()->user();

        $cartMenu = CartMenu::where('id', $id)
            ->whereHas('cart', function ($query) use ($chair) {
                $query->where('chair_id', $chair->id)
                    ->whereDoesntHave('orders');
            })
            ->firstOrFail();

        $cart = $cartMenu->cart;
        $subtotal = $cartMenu->subtotal;
        $cartMenu->delete();

        $cart->update([
            'total_amount' => $cart->total_amount - $subtotal,
            'expires_at' => now()->addMinutes(Cart::EXPIRATION_MINUTES),
        ]);

        return redirect()->route('user-cart');
    }

    public function acknowledge(Request $request)
    {
        $request->session()->put('cart_acknowledged', true);

        return redirect()->route('user-home');
    }

    public function reset(Request $request)
    {
        $chair = auth()->user();

        $cart = $chair->carts()
            ->whereDoesntHave('orders')
            ->latest()
            ->first();

        if ($cart) {
            $cart->cartMenus()->delete();
            $cart->update([
                'total_amount' => 0,
                'expires_at' => now()->addMinutes(Cart::EXPIRATION_MINUTES),
            ]);
        }

        $request->session()->put('cart_acknowledged', true);

        return redirect()->route('user-home')->with('success', 'Mulai pesanan baru.');
    }
}
