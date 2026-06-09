<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SocialFeed extends Model {
    protected $fillable=['product_id','shop_id','video_url','caption','is_active','views','likes'];
    protected function casts():array{return['is_active'=>'boolean'];}
    public function product(){return $this->belongsTo(Product::class);}
    public function shop(){return $this->belongsTo(Shop::class);}
}
