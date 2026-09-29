<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['customer_id', 'label', 'receiver_name', 'receiver_phone', 'address', 'city', 'province', 'postal_code', 'shipping_destination_id', 'latitude', 'longitude', 'is_default'])]
class CustomerAddress extends Model
{
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public const LABELS = ['Rumah', 'Kantor', 'Kos', 'Orang Tua', 'Lainnya'];

    /** Normalisasi multi-label + alamat utama (tanpa kolom baru). */
    public static function normalize(array $data): array
    {
        $label = trim((string) ($data['label'] ?? ''));
        if ($label === '') {
            $label = 'Rumah';
        } elseif (! in_array($label, self::LABELS, true) && mb_strlen($label) > 60) {
            $label = mb_substr($label, 0, 60);
        }
        $data['label'] = mb_convert_case($label, MB_CASE_TITLE, 'UTF-8');
        foreach (['receiver_name', 'city', 'province', 'address'] as $key) {
            if (isset($data[$key]) && is_string($data[$key])) {
                $data[$key] = preg_replace('/\s+/', ' ', trim($data[$key]));
            }
        }
        if (isset($data['postal_code']) && is_string($data['postal_code'])) {
            $data['postal_code'] = preg_replace('/[^0-9]/', '', $data['postal_code']);
        }
        if (isset($data['receiver_phone']) && is_string($data['receiver_phone'])) {
            $phone = preg_replace('/[^0-9+]/', '', trim($data['receiver_phone']));
            if (str_starts_with($phone, '0')) {
                $phone = '+62'.substr($phone, 1);
            }
            $data['receiver_phone'] = $phone;
        }

        return $data;
    }

    public function formatted(): string
    {
        return trim(implode(', ', array_filter([
            (string) ($this->address ?? ''), (string) ($this->city ?? ''),
            (string) ($this->province ?? ''), (string) ($this->postal_code ?? ''),
        ])));
    }
}
