<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentTranslation extends Model
{
    protected $fillable = ['subject_type', 'subject_id', 'subject_key', 'locale', 'title', 'slug', 'body', 'meta_title', 'meta_description', 'status'];
}
