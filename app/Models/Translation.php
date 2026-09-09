<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Translation extends Model
{
    protected $fillable = ['locale', 'group', 'key', 'value'];

    public static function get($locale, $group, $key, $default = null)
    {
        return static::where(compact('locale', 'group', 'key'))->value('value') ?? $default;
    }

    public static function set($locale, $group, $key, $value)
    {
        return static::updateOrCreate(compact('locale', 'group', 'key'), ['value' => $value]);
    }
}
