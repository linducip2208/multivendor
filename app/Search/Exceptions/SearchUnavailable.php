<?php

declare(strict_types=1);

namespace App\Search\Exceptions;

use RuntimeException;
use Throwable;

class SearchUnavailable extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $driver = 'unknown',
        public readonly string $reason = 'unavailable',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function connection(string $driver, string $endpoint, Throwable $previous): self
    {
        return new self(
            sprintf('Search driver [%s] is unreachable at %s: %s', $driver, $endpoint, $previous->getMessage()),
            $driver,
            'connection',
            0,
            $previous,
        );
    }

    public static function response(string $driver, int $status, string $body = ''): self
    {
        return new self(
            sprintf('Search driver [%s] responded with HTTP %d: %s', $driver, $status, mb_substr($body, 0, 400)),
            $driver,
            'response',
            $status,
        );
    }

    public static function misconfigured(string $driver, string $key): self
    {
        return new self(
            sprintf('Search driver [%s] is misconfigured: %s is empty.', $driver, $key),
            $driver,
            'misconfigured',
        );
    }
}
