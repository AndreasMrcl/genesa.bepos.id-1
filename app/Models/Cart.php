<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    use HasFactory;

    protected $fillable =
        [
            'store_id',
            'user_id',
            'chair_id',
            'customer_name',
            'total_amount',
            'expires_at',
            'is_open_bill',
            'opened_at',
        ];

    protected $casts = [
        'expires_at' => 'datetime',
        'opened_at' => 'datetime',
        'is_open_bill' => 'boolean',
    ];

    public const EXPIRATION_MINUTES = 30;

    public function scopeActive($query)
    {
        return $query->whereDoesntHave('orders')
            ->whereHas('cartMenus')
            ->where('is_open_bill', false)
            ->where('expires_at', '>', now());
    }

    public function scopeOpenBills($query)
    {
        return $query->where('is_open_bill', true)->whereDoesntHave('orders');
    }

    public static function getActiveOrCreateForChair(Chair $chair): self
    {
        $cart = $chair->carts()
            ->where('is_open_bill', false)
            ->whereDoesntHave('orders')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->latest()
            ->first();

        if (! $cart) {
            $cart = $chair->carts()->create([
                'store_id' => $chair->store_id,
                'expires_at' => now()->addMinutes(self::EXPIRATION_MINUTES),
            ]);
        }

        return $cart;
    }

    public function bumpExpiration(): void
    {
        $this->update(['expires_at' => now()->addMinutes(self::EXPIRATION_MINUTES)]);
    }

    /**
     * Pending online order yang mengunci cart ini (belum settle/gagal).
     * Selama ini ada, cart tidak boleh diubah.
     */
    public function pendingOnlineOrder(): ?Order
    {
        return $this->orders()
            ->where('payment_type', 'online')
            ->where(function ($query) {
                $query->whereNull('status')->orWhere('status', 'pending');
            })
            ->latest('id')
            ->first();
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function chair()
    {
        return $this->belongsTo(Chair::class);
    }

    public function cartMenus()
    {
        return $this->hasMany(CartMenu::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}