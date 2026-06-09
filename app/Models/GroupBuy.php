<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class GroupBuy extends Model {
    protected $fillable=['product_id','target_count','current_count','discount_percentage','special_price','end_date','is_active'];
    protected function casts():array{return['discount_percentage'=>'decimal:2','special_price'=>'decimal:2','end_date'=>'datetime','is_active'=>'boolean'];}
    public function product(){return $this->belongsTo(Product::class);}
    public function participants(){return $this->hasMany(GroupBuyParticipant::class);}
}
class GroupBuyParticipant extends Model {
    protected $fillable=['group_buy_id','customer_id'];
}
