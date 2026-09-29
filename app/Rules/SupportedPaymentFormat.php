<?php

declare(strict_types=1);

namespace App\Rules;

use App\Services\Payment\PaymentGatewayService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SupportedPaymentFormat implements ValidationRule
{
    public const REQUIRE_REDIRECT = 'redirect';

    public const REQUIRE_API = 'api';

    public const REQUIRE_CALLBACK = 'callback';

    public const REQUIRE_REFUND = 'refund';

    public function __construct(private readonly string $requirement = self::REQUIRE_REDIRECT) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $format = is_string($value) ? trim($value) : '';

        if ($format === '' || ! PaymentGatewayService::isSupportedFormat($format)) {
            $fail('Format gateway pembayaran ini tidak didukung.');

            return;
        }

        $allowed = match ($this->requirement) {
            self::REQUIRE_API => PaymentGatewayService::supportedApiFormats(),
            self::REQUIRE_CALLBACK => array_values(array_filter(
                PaymentGatewayService::supportedFormats(),
                static fn (string $format): bool => PaymentGatewayService::supportsCallbackVerification($format),
            )),
            self::REQUIRE_REFUND => array_values(array_filter(
                PaymentGatewayService::supportedFormats(),
                static fn (string $format): bool => PaymentGatewayService::supportsRefunds($format),
            )),
            default => PaymentGatewayService::supportedRedirectFormats(),
        };

        if (! in_array($format, $allowed, true)) {
            $fail('Format gateway pembayaran ini tidak dapat digunakan untuk '.$this->label().'. Pilihan: '.implode(', ', $allowed).'.');
        }
    }

    private function label(): string
    {
        return match ($this->requirement) {
            self::REQUIRE_API => 'pembayaran berbasis API',
            self::REQUIRE_CALLBACK => 'verifikasi callback',
            self::REQUIRE_REFUND => 'eksekusi refund',
            default => 'pembayaran redirect',
        };
    }
}
