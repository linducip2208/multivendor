<?php

declare(strict_types=1);

namespace App\Search;

use App\Search\Contracts\SearchDriver;
use App\Search\Drivers\DatabaseSearchDriver;
use App\Search\Exceptions\SearchUnavailable;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use Throwable;

/**
 * Search facade / driver registry.
 *
 * The active driver is selected with `SEARCH_DRIVER` (`database` by default).
 * External drivers are declared in `config/search.php` and may also be
 * registered imperatively with {@see SearchManager::extend()}. When an external
 * driver raises {@see SearchUnavailable} the manager silently degrades to the
 * fallback driver, so a search outage can never take the storefront down.
 */
class SearchManager
{
    /** @var array<string, class-string<SearchDriver>> */
    private array $customDrivers = [];

    private ?string $degradedDriver = null;

    public function __construct(private readonly Container $container) {}

    public function extend(string $name, string $driver): void
    {
        $this->customDrivers[$name] = $driver;
    }

    public function driver(?string $name = null): SearchDriver
    {
        $name = $name ?: (string) config('search.default', 'database');

        $instance = $this->resolve($name);
        if ($instance->name() !== 'database' && $this->isTripped($name)) {
            return $this->container->make(DatabaseSearchDriver::class);
        }

        return $instance;
    }

    public function search(SearchQuery $query): SearchResult
    {
        try {
            return $this->driver($query->driver ?? null)->search($query);
        } catch (SearchUnavailable $exception) {
            $this->trip($exception->driver);

            return $this->fallbackDriver()->search($query);
        } catch (Throwable $exception) {
            report($exception);

            return $this->fallbackDriver()->search($query);
        }
    }

    /** @return array<string, mixed> */
    public function suggest(string $term, int $limit = 6): array
    {
        try {
            return $this->driver()->suggest($term, $limit);
        } catch (SearchUnavailable $exception) {
            $this->trip($exception->driver);

            return $this->fallbackDriver()->suggest($term, $limit);
        } catch (Throwable $exception) {
            report($exception);

            return $this->fallbackDriver()->suggest($term, $limit);
        }
    }

    /** @return list<string> */
    public function availableDrivers(): array
    {
        $configured = array_keys((array) config('search.drivers', []));

        return array_values(array_unique(array_merge(['database'], $configured, array_keys($this->customDrivers))));
    }

    public function isExternal(): bool
    {
        return $this->driver()->name() !== 'database';
    }

    public function activeDriver(): string
    {
        return $this->driver()->name();
    }

    public function degradedDriver(): ?string
    {
        return $this->degradedDriver;
    }

    private function fallbackDriver(): SearchDriver
    {
        $fallback = (string) config('search.fallback', 'database');

        if ($fallback === '' || $fallback === 'database') {
            return $this->container->make(DatabaseSearchDriver::class);
        }

        try {
            return $this->resolve($fallback);
        } catch (InvalidArgumentException) {
            return $this->container->make(DatabaseSearchDriver::class);
        }
    }

    private function resolve(string $name): SearchDriver
    {
        if ($name === 'database') {
            return $this->container->make(DatabaseSearchDriver::class);
        }

        $configured = (array) config('search.drivers', []);
        $class = $this->customDrivers[$name] ?? ($configured[$name] ?? null);

        if ($class === null) {
            throw new InvalidArgumentException("Unsupported search driver [{$name}]. Register it with SearchManager::extend().");
        }

        $instance = $this->container->make($class);

        if (! $instance instanceof SearchDriver) {
            throw new InvalidArgumentException("Search driver [{$class}] must implement ".SearchDriver::class.'.');
        }

        return $instance;
    }

    private function isTripped(string $name): bool
    {
        if (! (bool) config('search.failover.enabled', true)) {
            return false;
        }

        try {
            $state = cache()->get($this->circuitKey($name));
        } catch (Throwable) {
            return false;
        }

        if (! is_array($state) || ! isset($state['until'])) {
            return false;
        }

        if ((float) $state['until'] <= microtime(true)) {
            $this->forget($this->circuitKey($name));

            return false;
        }

        $this->degradedDriver = $name;

        return true;
    }

    private function trip(string $name): void
    {
        if ($name === '' || $name === 'database') {
            return;
        }

        $this->degradedDriver = $name;
        $cooldown = (int) config('search.failover.cooldown', 60);

        try {
            cache()->put($this->circuitKey($name), ['until' => microtime(true) + $cooldown], $cooldown + 5);
        } catch (Throwable) {
        }
    }

    private function circuitKey(string $name): string
    {
        return ((string) config('search.failover.cache_key', 'search:failover:state')).':'.$name;
    }

    private function forget(string $key): void
    {
        try {
            cache()->forget($key);
        } catch (Throwable) {
        }
    }
}
