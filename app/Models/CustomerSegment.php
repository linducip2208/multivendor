<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'description', 'type', 'rules', 'member_count', 'is_dynamic'])]
class CustomerSegment extends Model
{
    protected function casts(): array
    {
        return [
            'rules' => 'array',
            'member_count' => 'integer',
            'is_dynamic' => 'boolean',
        ];
    }

    public function members(): HasMany
    {
        return $this->hasMany(CustomerSegmentMember::class);
    }
}
