<?php

declare(strict_types=1);

namespace App\Services\Payment;

use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use Throwable;

final class PaymentLog
{
    private const SENSITIVE_TOKENS = [
        'api_key', 'api_secret', 'apikey', 'secret', 'password', 'passwd',
        'token', 'authorization', 'auth', 'signature', 'sign_key', 'signature_key',
        'callback_token', 'callback_signature', 'cookie', 'session', 'card', 'card_number',
        'cardnumber', 'cvv', 'cvc', 'email', 'e_mail', 'phone', 'mobile',
        'x-callback-token', 'x-callback-signature', 'server_key', 'basic',
    ];

    /** Max logged string length; longer values are truncated to bound PII/card-data exposure. */
    public const MAX_STRING_LENGTH = 500;

    public const REDACTED = '[redacted]';

    public static function channel(string $level, string $message, array $context = []): void
    {
        self::logger()->log($level, $message, self::redact($context));
    }

    public static function redact(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && self::isSensitive((string) $key)) {
            return self::REDACTED;
        }

        if (is_array($value)) {
            $out = [];
            foreach ($value as $childKey => $childValue) {
                $out[$childKey] = self::redact($childValue, is_string($childKey) ? $childKey : null);
            }

            return $out;
        }

        if (is_string($value)) {
            return self::truncate(self::maskBearer($value));
        }

        return $value;
    }

    public static function maskBearer(string $value): string
    {
        $masked = preg_replace('/(Basic|Bearer)\s+[A-Za-z0-9\-\._~\+\/=]+/i', '$1 '.self::REDACTED, $value);

        return is_string($masked) ? $masked : $value;
    }

    public static function truncate(string $value): string
    {
        if (mb_strlen($value) > self::MAX_STRING_LENGTH) {
            return mb_substr($value, 0, self::MAX_STRING_LENGTH).'…[truncated]';
        }

        return $value;
    }

    private static function isSensitive(string $key): bool
    {
        $normalised = strtolower(str_replace(['-', '_'], '', $key));

        foreach (self::SENSITIVE_TOKENS as $token) {
            if (str_contains($normalised, str_replace(['-', '_'], '', $token))) {
                return true;
            }
        }

        return false;
    }

    private static function logger(): LoggerInterface
    {
        try {
            if (config('logging.channels.payment') !== null) {
                return Log::channel('payment');
            }
        } catch (Throwable) {
        }

        return Log::channel((string) config('logging.default', 'stack'));
    }
}
