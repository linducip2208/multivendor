<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CustomerBadge extends Model {
    protected $fillable=['customer_id','badge','tier'];
    public function customer(){return $this->belongsTo(User::class,'customer_id');}
}
