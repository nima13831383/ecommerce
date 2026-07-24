<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// app/Models/FlashSaleItem.php
class FlashSaleItem extends Model
{
    protected $fillable = ['flash_sale_id', 'product_id', 'sale_price', 'quantity_limit', 'sold_count'];
    protected $casts = ['sale_price' => 'integer', 'sold_count' => 'integer'];

    public function flashSale()
    {
        return $this->belongsTo(FlashSale::class);
    }
    public function product()
    {
        return $this->belongsTo(Product::class)->withDefault();
    }
}
