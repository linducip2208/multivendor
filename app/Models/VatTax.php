<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class VatTax extends Model {
    protected $fillable = ['name','rate','is_active'];
    protected function casts(): array { return ['rate'=>'decimal:2','is_active'=>'boolean']; }
}
