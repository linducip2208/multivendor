<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PriceAlert extends Model { protected $fillable=['customer_id','product_id','target_price','notified','notified_at']; protected function casts():array{return['target_price'=>'decimal:2','notified'=>'boolean','notified_at'=>'datetime'];} }
class ProductBundle extends Model { protected $fillable=['title','discount_percentage','is_active']; public function products(){return $this->belongsToMany(Product::class,'bundle_products','bundle_id','product_id');} }
class GroupBuy extends Model { protected $fillable=['product_id','target_count','current_count','discount_percentage','special_price','end_date','is_active']; protected function casts():array{return['discount_percentage'=>'decimal:2','special_price'=>'decimal:2','end_date'=>'datetime','is_active'=>'boolean'];} public function product(){return $this->belongsTo(Product::class);} public function participants(){return $this->hasMany(GroupBuyParticipant::class);} }
class GroupBuyParticipant extends Model { protected $fillable=['group_buy_id','customer_id']; }
class CustomerBadge extends Model { protected $fillable=['customer_id','badge','tier']; public function customer(){return $this->belongsTo(User::class,'customer_id');} }
class SocialFeed extends Model { protected $fillable=['product_id','shop_id','video_url','caption','is_active','views','likes']; public function product(){return $this->belongsTo(Product::class);} public function shop(){return $this->belongsTo(Shop::class);} }
