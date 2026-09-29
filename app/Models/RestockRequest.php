<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RestockRequest extends Model {
    protected $fillable = ['product_id','customer_id','status'];
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function customer(): BelongsTo { return $this->belongsTo(User::class,'customer_id'); }
}
