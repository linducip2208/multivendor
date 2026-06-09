<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopFollower extends Model {
    protected $fillable = ['shop_id','customer_id'];
    public function shop(): BelongsTo { return $this->belongsTo(Shop::class); }
    public function customer(): BelongsTo { return $this->belongsTo(User::class,'customer_id'); }
}

class RestockRequest extends Model {
    protected $fillable = ['product_id','customer_id','status'];
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function customer(): BelongsTo { return $this->belongsTo(User::class,'customer_id'); }
}
