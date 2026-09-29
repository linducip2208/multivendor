<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['customer_id', 'subject', 'type', 'priority', 'description', 'status', 'assigned_to', 'resolved_at', 'shop_id', 'vendor_id', 'reference', 'order_id', 'first_response_at', 'closed_at'])]
class SupportTicket extends Model
{
    protected function casts(): array
    {
        return ['resolved_at' => 'datetime', 'first_response_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(SupportTicketReply::class);
    }

    public const SLA_HOURS = ['low' => 72, 'medium' => 48, 'normal' => 48, 'high' => 24, 'urgent' => 8];

    /** SLA komputasi dari priority + created_at (tanpa kolom baru). */
    public function sla(): array
    {
        $priority = strtolower((string) ($this->priority ?? 'medium'));
        $hours = self::SLA_HOURS[$priority] ?? 48;
        $due = $this->created_at ? $this->created_at->copy()->addHours($hours) : null;
        $resolved = $this->resolved_at ?? $this->closed_at;
        $breached = $due !== null && $resolved !== null ? $resolved->greaterThan($due) : ($due !== null && $resolved === null && now()->greaterThan($due));
        $remaining = $due !== null && $resolved === null ? $due->diffForHumans() : null;

        return [
            'priority' => $priority, 'target_hours' => $hours,
            'due_at' => $due?->toDateTimeString(), 'due_human' => $due?->diffForHumans(),
            'resolved_at' => $resolved?->toDateTimeString(),
            'breached' => $breached, 'remaining' => $remaining,
            'status_label' => $breached ? 'SLA terlampaui' : 'Dalam SLA',
        ];
    }

    /** CSAT komputasi dari pola balasan (skor 1-5 bila ada penanda puas/kecewa). */
    public function csat(): array
    {
        try {
            $replies = $this->replies()->with('user')->orderBy('id')->get();
            $score = null;
            foreach ($replies as $reply) {
                $text = mb_strtolower((string) $reply->message);
                if (str_contains($text, 'sangat puas') || str_contains($text, 'csat:5') || str_contains($text, '★★★★★')) {
                    $score = 5;
                } elseif (str_contains($text, 'puas') || str_contains($text, 'csat:4') || str_contains($text, '★★★★')) {
                    $score ??= 4;
                } elseif (str_contains($text, 'kecewa') || str_contains($text, 'csat:1') || str_contains($text, '★☆')) {
                    $score ??= 2;
                }
            }
            $firstResponse = $this->first_response_at
                ?? optional($replies->first(fn ($r) => (int) $r->user_id !== (int) $this->customer_id))->created_at;

            return [
                'score' => $score, 'label' => $score === null ? 'Belum ada penilaian' : $score.' dari 5',
                'responses' => $replies->count(),
                'first_response_at' => $firstResponse?->toDateTimeString(),
                'first_response_human' => $firstResponse?->diffForHumans(),
            ];
        } catch (\Throwable) {
            return ['score' => null, 'label' => 'Belum ada penilaian', 'responses' => 0, 'first_response_at' => null, 'first_response_human' => null];
        }
    }

    /** Riwayat makro: balasan dikelompokkan per hari + perubahan status. */
    public function macroHistory(): array
    {
        try {
            $groups = [];
            foreach ($this->replies()->orderBy('id')->get() as $reply) {
                $day = $reply->created_at?->format('Y-m-d') ?? 'tanpa-tanggal';
                $groups[$day][] = [
                    'id' => (int) $reply->id,
                    'author' => (string) ($reply->user?->name ?? 'CS'),
                    'message' => (string) $reply->message,
                    'at' => (string) ($reply->created_at?->format('H:i') ?? ''),
                ];
            }
            ksort($groups);

            return array_map(fn ($day, $items) => ['date' => $day, 'items' => $items], array_keys($groups), array_values($groups));
        } catch (\Throwable) {
            return [];
        }
    }
}
