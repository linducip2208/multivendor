<?php

declare(strict_types=1);

namespace App\Shipping\Providers;

use App\Shipping\AbstractShippingProvider;

final class LocalShippingProvider extends AbstractShippingProvider
{
    protected function code(): string { return 'local'; }
    protected function displayName(): string { return 'Local Courier'; }
    protected function baseRatePerKg(): float { return 10000.0; }
    protected function baseFee(): float { return 8000.0; }
    protected function etaDays(): int { return 2; }
    protected function supportedServices(): array { return ['reguler', 'same_day', 'next_day']; }
    protected function supportedCountries(): array { return ['ID']; }
    protected function maxWeightKg(): float { return 100.0; }
}
