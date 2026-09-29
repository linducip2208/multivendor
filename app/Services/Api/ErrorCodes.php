<?php

declare(strict_types=1);

namespace App\Services\Api;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Maps every failure onto one machine readable code.
 *
 * A handler contract is `order_not_found`, `insufficient_stock`, `rate_limited`,
 * `validation_failed` and so on. Codes are stable API surface; messages are not.
 */
final class ErrorCodes
{
    public const VALIDATION_FAILED = 'validation_failed';

    public const UNAUTHENTICATED = 'unauthenticated';

    public const FORBIDDEN = 'forbidden';

    public const RATE_LIMITED = 'rate_limited';

    public const NOT_FOUND = 'resource_not_found';

    public const METHOD_NOT_ALLOWED = 'method_not_allowed';

    public const UNSUPPORTED_MEDIA_TYPE = 'unsupported_media_type';

    public const PAYLOAD_TOO_LARGE = 'payload_too_large';

    public const CONFLICT = 'conflict';

    public const IDEMPOTENCY_KEY_REUSED = 'idempotency_key_reused';

    public const IDEMPOTENT_REQUEST_IN_FLIGHT = 'idempotent_request_in_flight';

    public const MISSING_SCOPE = 'missing_scope';

    public const INSUFFICIENT_STOCK = 'insufficient_stock';

    public const PRODUCT_NOT_AVAILABLE = 'product_not_available';

    public const SHOP_NOT_AVAILABLE = 'shop_not_available';

    public const INSUFFICIENT_WALLET_BALANCE = 'insufficient_wallet_balance';

    public const COUPON_INVALID = 'coupon_invalid';

    public const ORDER_NOT_CANCELABLE = 'order_not_cancelable';

    public const DOMAIN_RULE_VIOLATION = 'domain_rule_violation';

    public const INTERNAL_ERROR = 'internal_error';

    private const NOT_FOUND_SUFFIXES = [
        'order' => 'order_not_found',
        'product' => 'product_not_found',
        'shop' => 'shop_not_found',
        'cart' => 'cart_item_not_found',
        'user' => 'user_not_found',
        'category' => 'category_not_found',
        'brand' => 'brand_not_found',
        'coupon' => 'coupon_not_found',
        'refund' => 'refund_not_found',
        'conversation' => 'conversation_not_found',
        'message' => 'message_not_found',
        'notification' => 'notification_not_found',
        'address' => 'address_not_found',
        'api_key' => 'api_key_not_found',
        'shipment' => 'shipment_not_found',
    ];

    public static function for(Throwable $e): string
    {
        return match (true) {
            $e instanceof ValidationException => self::VALIDATION_FAILED,
            $e instanceof AuthenticationException => self::UNAUTHENTICATED,
            $e instanceof AuthorizationException => self::FORBIDDEN,
            $e instanceof ThrottleRequestsException => self::RATE_LIMITED,
            $e instanceof ModelNotFoundException => self::modelNotFound($e),
            $e instanceof \DomainException => self::DOMAIN_RULE_VIOLATION,
            $e instanceof InsufficientStockException => self::INSUFFICIENT_STOCK,
            $e instanceof \InvalidArgumentException => self::VALIDATION_FAILED,
            $e instanceof HttpResponseException => self::fromStatus($e->getResponse()->getStatusCode()),
            $e instanceof NotFoundHttpException => self::NOT_FOUND,
            $e instanceof HttpExceptionInterface => self::fromStatus($e->getStatusCode()),
            default => self::INTERNAL_ERROR,
        };
    }

    public static function statusFor(Throwable $e): int
    {
        return match (true) {
            $e instanceof ValidationException => 422,
            $e instanceof AuthenticationException => 401,
            $e instanceof AuthorizationException => 403,
            $e instanceof ThrottleRequestsException => 429,
            $e instanceof ModelNotFoundException => 404,
            $e instanceof \DomainException => 422,
            $e instanceof InsufficientStockException => 422,
            $e instanceof \InvalidArgumentException => 422,
            $e instanceof HttpResponseException => $e->getResponse()->getStatusCode(),
            $e instanceof HttpExceptionInterface => $e->getStatusCode(),
            default => 500,
        };
    }

    public static function fromStatus(int $status): string
    {
        return match ($status) {
            400 => 'bad_request',
            401 => self::UNAUTHENTICATED,
            403 => self::FORBIDDEN,
            404 => self::NOT_FOUND,
            405 => self::METHOD_NOT_ALLOWED,
            409 => self::CONFLICT,
            413 => self::PAYLOAD_TOO_LARGE,
            415 => self::UNSUPPORTED_MEDIA_TYPE,
            422 => self::VALIDATION_FAILED,
            429 => self::RATE_LIMITED,
            default => $status >= 500 ? self::INTERNAL_ERROR : 'bad_request',
        };
    }

    public static function messageFor(Throwable $e, bool $debug = false): string
    {
        $status = self::statusFor($e);

        if ($status >= 500) {
            return $debug
                ? $e->getMessage()
                : 'Terjadi kesalahan pada server. Gunakan request_id untuk melacak.';
        }

        $message = $e->getMessage();

        return $message === '' ? self::defaultMessage($status) : $message;
    }

    public static function errorsFor(Throwable $e): array
    {
        if ($e instanceof ValidationException) {
            return $e->errors();
        }

        if ($e instanceof InsufficientStockException) {
            return ['quantity' => ['Stok tersedia: '.$e->available.'.']];
        }

        return [];
    }

    public static function headersFor(Throwable $e): array
    {
        if ($e instanceof ThrottleRequestsException) {
            return $e->getHeaders();
        }

        if ($e instanceof HttpResponseException) {
            return $e->getResponse()->headers->all();
        }

        if ($e instanceof HttpExceptionInterface) {
            return $e->getHeaders();
        }

        return [];
    }

    public static function isClientSafe(Throwable $e): bool
    {
        return self::statusFor($e) < 500;
    }

    private static function modelNotFound(ModelNotFoundException $e): string
    {
        $id = (string) $e->getModel();

        if ($id !== '' && class_exists($id)) {
            $short = (new \ReflectionClass($id))->getShortName();
            $snake = \Illuminate\Support\Str::of($short)->snake()->toString();

            if (isset(self::NOT_FOUND_SUFFIXES[$snake])) {
                return self::NOT_FOUND_SUFFIXES[$snake];
            }
        }

        foreach (self::NOT_FOUND_SUFFIXES as $needle => $code) {
            if (str_contains(strtolower($e->getMessage()), $needle)) {
                return $code;
            }
        }

        return self::NOT_FOUND;
    }

    private static function defaultMessage(int $status): string
    {
        return match ($status) {
            401 => 'Autentikasi diperlukan.',
            403 => 'Anda tidak memiliki akses ke sumber daya ini.',
            404 => 'Sumber daya tidak ditemukan.',
            405 => 'Metode HTTP tidak diizinkan.',
            409 => 'Permintaan konflik dengan state saat ini.',
            413 => 'Payload terlalu besar.',
            415 => 'Media type tidak didukung.',
            422 => 'Data yang dikirim tidak valid.',
            429 => 'Terlalu banyak permintaan. Coba lagi nanti.',
            default => 'Permintaan tidak dapat diproses.',
        };
    }
}
