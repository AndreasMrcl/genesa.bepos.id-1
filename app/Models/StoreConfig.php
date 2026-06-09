<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'currency',
        'tax_percent',
        'service_percent',
        'tax_active',
        'service_active',
        'checker_active',
        'min_stock_alert',
        'auto_archive_days',
        'receipt_header',
        'receipt_footer',
    ];

    protected $casts = [
        'tax_percent' => 'decimal:2',
        'service_percent' => 'decimal:2',
        'tax_active' => 'boolean',
        'service_active' => 'boolean',
        'checker_active' => 'boolean',
        'min_stock_alert' => 'integer',
        'auto_archive_days' => 'integer',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}