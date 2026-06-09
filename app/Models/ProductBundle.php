<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ProductBundle extends Model {
    protected $fillable=['title','discount_percentage','is_active'];
    protected function casts():array{return['is_active'=>'boolean','discount_percentage'=>'decimal:2'];}
    public function products(){return $this->belongsToMany(Product::class,'bundle_products','bundle_id','product_id');}
}
