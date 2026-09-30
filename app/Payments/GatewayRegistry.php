<?php

declare(strict_types=1);

namespace App\Payments;

/**
 * Registri gateway dengan dynamic discovery / Gateway registry.
 *
 * - register(): daftarkan factory/kelas secara eksplisit.
 * - discover(): daftarkan semua kelas *Gateway di namespace App\Payments
 *   yang mengimplementasikan PaymentGatewayInterface (saat ini: Fake).
 */
final class GatewayRegistry
{
    /** @var array<string, callable(): PaymentGatewayInterface> */
    private array $factories = [];

    /** @var array<string, PaymentGatewayInterface> */
    private array $instances = [];

    private CapabilityMatrix $capabilities;

    public function __construct(?CapabilityMatrix $capabilities = null)
    {
        $this->capabilities = $capabilities ?? new CapabilityMatrix();
        $this->discover();
    }

    public function capabilities(): CapabilityMatrix
    {
        return $this->capabilities;
    }

    /**
     * @param callable(): PaymentGatewayInterface|class-string<PaymentGatewayInterface> $factory
     */
    public function register(string $name, callable|string $factory, ?array $capability = null): self
    {
        $key = strtolower($name);
        $this->factories[$key] = is_string($factory)
            ? static fn (): PaymentGatewayInterface => new $factory()
            : $factory;

        if ($capability !== null) {
            $this->capabilities->define(
                $key,
                $capability['currencies'] ?? [],
                $capability['countries'] ?? [],
                $capability['methods'] ?? []
            );
        }

        unset($this->instances[$key]);

        return $this;
    }

    /** Dynamic discovery: semua *Gateway di App\Payments yang valid. */
    public function discover(): self
    {
        $dir = __DIR__;
        foreach (glob($dir.'/*Gateway.php') ?: [] as $file) {
            $class = 'App\\Payments\\'.basename($file, '.php');
            if (! class_exists($class)) {
                continue;
            }
            $implements = class_implements($class) ?: [];
            if (! in_array(PaymentGatewayInterface::class, $implements, true)) {
                continue;
            }
            if ((new \ReflectionClass($class))->isAbstract()) {
                continue;
            }
            try {
                $probe = (new \ReflectionClass($class))->newInstanceWithoutConstructor();
            } catch (\Throwable) {
                continue;
            }
            if (! method_exists($probe, 'getName')) {
                continue;
            }
            // FakePaymentGateway dapat diinstansiasi tanpa argumen (semua param opsional).
            try {
                $gateway = new $class();
            } catch (\Throwable) {
                continue;
            }
            if (! $gateway instanceof PaymentGatewayInterface) {
                continue;
            }
            $key = strtolower($gateway->getName());
            if (! isset($this->factories[$key])) {
                $this->factories[$key] = static fn (): PaymentGatewayInterface => new $class();
            }
        }

        return $this;
    }

    public function has(string $name): bool
    {
        return isset($this->factories[strtolower($name)]);
    }

    public function resolve(string $name): PaymentGatewayInterface
    {
        $key = strtolower($name);
        if (! isset($this->factories[$key])) {
            throw new PaymentException(
                "Gateway '{$name}' tidak terdaftar. / Gateway '{$name}' is not registered.",
                'Gateway tidak terdaftar.',
                'Gateway is not registered.',
                ['gateway' => $name]
            );
        }

        return $this->instances[$key] ??= ($this->factories[$key])();
    }

    /** @return string[] */
    public function names(): array
    {
        return array_keys($this->factories);
    }
}
