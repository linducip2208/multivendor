<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'route_pattern', 'title_template', 'description_template', 'intro_template', 'quality_threshold', 'is_indexable', 'is_enabled'])]
class PseoTemplate extends Model
{
    protected function casts(): array
    {
        return [
            'quality_threshold' => 'integer',
            'is_indexable' => 'boolean',
            'is_enabled' => 'boolean',
        ];
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(PseoPage::class, 'template_code', 'code');
    }
}
