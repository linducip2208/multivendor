<?php

declare(strict_types=1);

namespace App\Shipping;

/**
 * Registri provider pengiriman dengan discovery jujur.
 * Tanpa API live: resolve hanya instansiasi lokal.
 */
final class ShippingRegistry
{
    /** @var array<string, class-string<ShippingProviderInterface>> */
    private array $factories = [];

    /** @var array<string, ShippingProviderInterface> */
    private array $instances = [];

    public function __construct()
    {
        $this->discover();
    }

    public function register(string $code, string $class): self
    {
        $this->factories[strtolower($code)] = $class;
        unset($this->instances[strtolower($code)]);

        return $this;
    }

    public function discover(): self
    {
        $dir = __DIR__.'/Providers';
        foreach (glob($dir.'/*.php') ?: [] as $file) {
            $class = 'App\\Shipping\\Providers\\'.basename($file, '.php');
            if (! class_exists($class)) {
                continue;
            }
            $implements = class_implements($class) ?: [];
            if (! in_array(ShippingProviderInterface::class, $implements, true)) {
                continue;
            }
            try {
                /** @var ShippingProviderInterface $probe */
                $probe = new $class();
            } catch (\Throwable) {
                continue;
            }
            $key = strtolower($probe->getCode());
            $this->factories[$key] ??= $class;
        }

        return $this;
    }

    public function has(string $code): bool
    {
        return isset($this->factories[strtolower($code)]);
    }

    public function resolve(string $code): ShippingProviderInterface
    {
        $key = strtolower($code);
        if (! isset($this->factories[$key])) {
            throw new ShippingException(
                'Provider pengiriman '.$code.' tidak terdaftar.',
                'Shipping provider '.$code.' is not registered.',
                ['provider' => $code]
            );
        }

        return $this->instances[$key] ??= new $this->factories[$key]();
    }

    /** @return string[] */
    public function codes(): array
    {
        return array_keys($this->factories);
    }

    /** Matriks kapabilitas jujur semua provider. */
    public function capabilityMatrix(): array
    {
        $out = [];
        foreach ($this->codes() as $code) {
            $out[$code] = $this->resolve($code)->capabilities();
        }

        return $out;
    }
}
