<?php

declare(strict_types=1);

namespace App\Services\Api;

use RuntimeException;

final class InsufficientStockException extends RuntimeException
{
    public function __construct(public readonly int $available)
    {
        parent::__construct('Stok tidak mencukupi.');
    }
}
