<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['template_code', 'slug', 'url', 'title', 'description', 'body', 'context', 'quality_score', 'quality_breakdown', 'state', 'indexability', 'canonical_url', 'product_count', 'generated_at', 'reviewed_at', 'published_at', 'stale_at'])]
class PseoPage extends Model
{
    protected function casts(): array
    {
        return [
            'context' => 'array',
            'quality_breakdown' => 'array',
            'quality_score' => 'integer',
            'product_count' => 'integer',
            'generated_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'published_at' => 'datetime',
            'stale_at' => 'datetime',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('state', 'published');
    }

    public function scopeStale(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->where('state', 'stale')->orWhereNotNull('stale_at');
        });
    }

    public function scopeIndexable(Builder $query): Builder
    {
        return $query->where('indexability', 'index')->where('state', 'published');
    }

    public function isIndexable(): bool
    {
        if ($this->indexability !== 'index' || $this->state !== 'published') {
            return false;
        }

        return (int) $this->quality_score >= $this->qualityThreshold();
    }

    public function qualityThreshold(): int
    {
        if ($this->relationLoaded('template')) {
            $template = $this->getRelation('template');

            if ($template !== null && $template->quality_threshold !== null) {
                return (int) $template->quality_threshold;
            }
        }

        try {
            return (int) SystemSetting::get('pseo_quality_threshold', 70);
        } catch (\Throwable) {
            return 70;
        }
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(PseoTemplate::class, 'template_code', 'code');
    }
}
