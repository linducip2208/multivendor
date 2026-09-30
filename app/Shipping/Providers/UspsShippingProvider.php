<?php

declare(strict_types=1);

namespace App\Shipping\Providers;

use App\Shipping\AbstractShippingProvider;

final class UspsShippingProvider extends AbstractShippingProvider
{
    protected function code(): string { return 'usps'; }
    protected function displayName(): string { return 'USPS'; }
    protected function baseRatePerKg(): float { return 38000.0; }
    protected function baseFee(): float { return 20000.0; }
    protected function etaDays(): int { return 7; }
    protected function supportedServices(): array { return ['priority', 'first_class', 'express']; }
    protected function supportedCountries(): array { return ['US', 'ID']; }
    protected function maxWeightKg(): float { return 31.0; }

    protected function supportsPickup(): bool { return false; }
}
