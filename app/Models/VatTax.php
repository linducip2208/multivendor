<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class VatTax extends Model {
    protected $fillable = ['name','rate','is_active'];
    protected function casts(): array { return ['rate'=>'decimal:2','is_active'=>'boolean']; }
}
class Translation extends Model {
    protected $fillable = ['locale','group','key','value'];
    public static function get($locale,$group,$key,$default=null) { $t = static::where(compact('locale','group','key'))->first(); return $t?->value ?? $default; }
    public static function set($locale,$group,$key,$value) { static::updateOrCreate(compact('locale','group','key'),['value'=>$value]); }
}
class DeliveryManEarning extends Model {
    protected $fillable = ['delivery_man_id','order_id','amount','description'];
    protected function casts(): array { return ['amount'=>'decimal:2']; }
    public function deliveryMan() { return $this->belongsTo(User::class,'delivery_man_id'); }
}
class DeliveryCashCollect extends Model {
    protected $fillable = ['delivery_man_id','order_id','amount','collected','collected_at'];
    protected function casts(): array { return ['amount'=>'decimal:2','collected'=>'boolean','collected_at'=>'datetime']; }
}
