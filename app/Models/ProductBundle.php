<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductBundle extends Model
{
    protected $fillable = ['title', 'discount_percentage', 'is_active'];

    public function products()
    {
        return $this->belongsToMany(Product::class, 'bundle_products', 'bundle_id', 'product_id');
    }
}
