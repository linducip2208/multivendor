<?php

declare(strict_types=1);

namespace App\Shipping\Providers;

use App\Shipping\AbstractShippingProvider;

final class UpsShippingProvider extends AbstractShippingProvider
{
    protected function code(): string { return 'ups'; }
    protected function displayName(): string { return 'UPS'; }
    protected function baseRatePerKg(): float { return 48000.0; }
    protected function baseFee(): float { return 28000.0; }
    protected function etaDays(): int { return 5; }
    protected function supportedServices(): array { return ['express', 'standard', 'saver']; }
    protected function supportedCountries(): array { return []; }
    protected function maxWeightKg(): float { return 70.0; }
}
