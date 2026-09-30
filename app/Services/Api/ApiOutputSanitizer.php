<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Services\HtmlSanitizer;

/**
 * Sanitasi output API (XSS): Resources tak boleh disentuh, jadi sanitasi
 * dilakukan di lapisan controller/service sebelum respons. Hanya field
 * konten-bebas pengguna yang diproses; id/angka/timestamp tak tersentuh.
 */
final class ApiOutputSanitizer
{
    /** Kunci yang dianggap konten pengguna dan perlu disanitasi. */
    private const TEXT_KEYS = [
        'comment', 'note', 'description', 'short_description', 'message',
        'name', 'label', 'receiver_name', 'address', 'city', 'review',
    ];

    public static function sanitize(mixed $data): mixed
    {
        if (is_string($data)) {
            return self::cleanString($data);
        }
        if (! is_array($data)) {
            return $data;
        }
        $out = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::TEXT_KEYS, true) && is_string($value)) {
                $out[$key] = self::cleanString($value);
            } elseif (is_array($value)) {
                $out[$key] = self::sanitize($value);
            } else {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    public static function cleanString(string $value): string
    {
        try {
            $clean = app(HtmlSanitizer::class)->sanitize($value);
            if (is_string($clean)) {
                return $clean;
            }
        } catch (\Throwable) {
        }

        return (string) strip_tags($value);
    }
}
