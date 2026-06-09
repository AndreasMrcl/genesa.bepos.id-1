<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    use BelongsToStore, HasFactory;

    protected $fillable = [
        'store_id',
        'invent_id',
        'user_id',
        'quantity',
        'stock_before',
        'type',
        'reference_type',
        'reference_id',
        'notes',
        'item_notes',
    ];

    public function invent()
    {
        return $this->belongsTo(Invent::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reference()
    {
        return $this->morphTo();
    }
}