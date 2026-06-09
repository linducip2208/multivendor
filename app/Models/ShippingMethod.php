<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'code', 'cost', 'duration', 'description', 'status'])]
class ShippingMethod extends Model
{
    protected function casts(): array
    {
        return ['cost' => 'decimal:2', 'status' => 'boolean'];
    }

    public function shopMethods(): HasMany
    {
        return $this->hasMany(ShopShippingMethod::class);
    }
}
