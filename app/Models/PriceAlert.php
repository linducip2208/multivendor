<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PriceAlert extends Model {
    protected $fillable=['customer_id','product_id','target_price','notified','notified_at'];
    protected function casts():array{return['target_price'=>'decimal:2','notified'=>'boolean','notified_at'=>'datetime'];}
}
