<?php

declare(strict_types=1);

namespace App\Shipping\Providers;

use App\Shipping\AbstractShippingProvider;

final class DhlShippingProvider extends AbstractShippingProvider
{
    protected function code(): string { return 'dhl'; }
    protected function displayName(): string { return 'DHL'; }
    protected function baseRatePerKg(): float { return 45000.0; }
    protected function baseFee(): float { return 25000.0; }
    protected function etaDays(): int { return 3; }
    protected function supportedServices(): array { return ['express', 'standard', 'economy']; }
    protected function supportedCountries(): array { return []; }
    protected function maxWeightKg(): float { return 70.0; }
}
