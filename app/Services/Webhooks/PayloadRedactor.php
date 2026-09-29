<?php

declare(strict_types=1);

namespace App\Services\Webhooks;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use stdClass;

/**
 * Strips credentials, payment card data and bank details out of every outbound
 * webhook body. Applied to the envelope immediately before it is signed, so a
 * payload can never leave the platform with a secret in it even if a caller
 * hands one over.
 */
final class PayloadRedactor
{
    public const REDACTED = '[REDACTED]';

    public const TRUNCATED = '[TRUNCATED]';

    public const MAX_DEPTH = 12;

    public const MAX_STRING_LENGTH = 4000;

    /** @var list<string> */
    private const BLOCKED_KEYS = [
        'password', 'passwordconfirmation', 'currentpassword', 'newpassword', 'oldpassword', 'passwd', 'pwd', 'pass',
        'secret', 'secretkey', 'clientsecret', 'appsecret', 'webhooksecret', 'signingsecret',
        'apikey', 'apikeyencrypted', 'apisecret', 'apisecretencrypted', 'accesskey', 'accesskeyid', 'secretaccesskey',
        'token', 'accesstoken', 'refreshtoken', 'idtoken', 'bearertoken', 'sessiontoken', 'csrftoken', 'authtoken',
        'authorization', 'auth', 'authenticate', 'credentials', 'credential', 'privatekey', 'privatekeypem',
        'cardnumber', 'cardno', 'cardpan', 'creditcard', 'debitcard', 'pan', 'cvv', 'cvc', 'cvv2', 'cid', 'csc',
        'iban', 'swift', 'swiftcode', 'sortcode', 'accountnumber', 'norek', 'rekeningnumber',
        'pin', 'otp', 'otpcode', 'pincode', 'mfccode', 'securitycode', 'signaturekey', 'hmackey',
    ];

    /** @var list<string> */
    private const BLOCKED_KEY_FRAGMENTS = [
        'password', 'secret', 'apikey', 'apisecret', 'token', 'credential', 'privatekey',
        'cardnumber', 'creditcard', 'debitcard', 'cvv', 'cvc', 'authorization', 'bankaccount',
        'bankname', 'bankcode', 'bankbranch', 'iban', 'swiftcode', 'norek', 'accesskey',
    ];

    /** @var list<string> */
    private const BLOCKED_KEY_PREFIXES = [
        'bank', 'cardnumber', 'cardpan', 'creditcard', 'debitcard', 'cvv', 'cvc',
    ];

    /** @var list<string> */
    private const SENSITIVE_VALUE_PATTERNS = [
        '/\beyJ[A-Za-z0-9_-]{8,}\.[A-Za-z0-9_-]{8,}\.[A-Za-z0-9_-]{8,}\b/',
        '/-----BEGIN[A-Z ]*PRIVATE KEY-----/',
        '/\b(?:sk|pk|rk)_(?:live|test)_[A-Za-z0-9]{8,}\b/',
        '/\bwhsec_[A-Za-z0-9]{8,}\b/',
        '/\b(?:ghp|gho|ghu|ghs|ghr)_[A-Za-z0-9]{16,}\b/',
        '/\bxox[baprs]-[A-Za-z0-9-]{10,}\b/',
        '/\bAKIA[0-9A-Z]{12,}\b/',
        '/\b(?:Basic|Bearer)\s+[A-Za-z0-9._~+\/=-]{8,}/i',
    ];

    private const CARD_PREFIXES = '/^(?:4\d{3}|5[1-5]\d{2}|2(?:2[2-9]\d|[3-6]\d{2}|7[01]\d|720)|3[47]\d{2}|6(?:011|5\d{2}|4[4-9]\d))\d{8,15}$/';

    /** @param array<array-key, mixed> $payload @return array<array-key, mixed> */
    public function redact(array $payload): array
    {
        return $this->walk($payload, null, 0);
    }

    public function isBlockedKey(string $key): bool
    {
        $normalized = self::normalize($key);

        if ($normalized === '') {
            return false;
        }

        if (in_array($normalized, self::BLOCKED_KEYS, true)) {
            return true;
        }

        foreach (self::BLOCKED_KEY_FRAGMENTS as $fragment) {
            if (str_contains($normalized, $fragment)) {
                return true;
            }
        }

        foreach (self::BLOCKED_KEY_PREFIXES as $prefix) {
            if (str_starts_with($normalized, $prefix)) {
                return true;
            }
        }

        return false;
    }

    public function looksSensitiveValue(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        $candidate = trim($value);

        if ($candidate === '') {
            return false;
        }

        foreach (self::SENSITIVE_VALUE_PATTERNS as $pattern) {
            if (preg_match($pattern, $candidate) === 1) {
                return true;
            }
        }

        $digits = (string) preg_replace('/[\s-]/', '', $candidate);

        if (preg_match(self::CARD_PREFIXES, $digits) === 1) {
            return true;
        }

        if (strlen($digits) >= 12 && strlen($digits) <= 19 && $this->passesLuhn($digits)) {
            return true;
        }

        return false;
    }

    public static function normalize(string $key): string
    {
        return (string) preg_replace('/[^a-z0-9]/', '', strtolower($key));
    }

    private function walk(mixed $value, ?string $key, int $depth): mixed
    {
        if ($key !== null && $this->isBlockedKey($key)) {
            return self::REDACTED;
        }

        if ($depth >= self::MAX_DEPTH) {
            return is_array($value) || is_object($value) ? self::TRUNCATED : $value;
        }

        if (is_array($value)) {
            $result = [];

            foreach ($value as $childKey => $childValue) {
                $result[$childKey] = $this->walk(
                    $childValue,
                    is_string($childKey) ? $childKey : null,
                    $depth + 1
                );
            }

            return $result;
        }

        if ($value instanceof stdClass || $value instanceof JsonSerializable || $value instanceof Arrayable) {
            return $this->walk($this->toPlainArray($value), $key, $depth + 1);
        }

        if (is_object($value)) {
            return $this->walk(get_object_vars($value), $key, $depth + 1);
        }

        if (is_string($value)) {
            if ($this->looksSensitiveValue($value)) {
                return self::REDACTED;
            }

            if (strlen($value) > self::MAX_STRING_LENGTH) {
                return substr($value, 0, self::MAX_STRING_LENGTH).self::TRUNCATED;
            }
        }

        return $value;
    }

    private function toPlainArray(object $value): array
    {
        if ($value instanceof Arrayable) {
            return $value->toArray();
        }

        if ($value instanceof JsonSerializable) {
            $serialized = $value->jsonSerialize();

            return is_array($serialized) ? $serialized : get_object_vars($value);
        }

        return get_object_vars($value);
    }

    private function passesLuhn(string $digits): bool
    {
        if (preg_match('/^\d+$/', $digits) !== 1) {
            return false;
        }

        $sum = 0;
        $alternate = false;

        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $digit = (int) $digits[$i];

            if ($alternate) {
                $digit *= 2;

                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
            $alternate = ! $alternate;
        }

        return $sum % 10 === 0;
    }
}
