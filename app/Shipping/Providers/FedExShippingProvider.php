<?php

declare(strict_types=1);

namespace App\Shipping\Providers;

use App\Shipping\AbstractShippingProvider;

final class FedExShippingProvider extends AbstractShippingProvider
{
    protected function code(): string { return 'fedex'; }
    protected function displayName(): string { return 'FedEx'; }
    protected function baseRatePerKg(): float { return 52000.0; }
    protected function baseFee(): float { return 30000.0; }
    protected function etaDays(): int { return 4; }
    protected function supportedServices(): array { return ['express', 'priority', 'economy']; }
    protected function supportedCountries(): array { return []; }
    protected function maxWeightKg(): float { return 68.0; }
}
