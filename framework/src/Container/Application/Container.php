<?php

declare(strict_types=1);

namespace AIC\Container\Application;

use AIC\Container\Contracts\ServiceRegistry;
use AIC\Container\Exception\ContainerException;
use AIC\Container\Exception\NotFoundException;
use Closure;
use Override;
use Psr\Container\ContainerInterface;
use Throwable;

use function array_key_exists;
use function count;
use function implode;
use function in_array;
use function is_object;

/**
 * The PSR-11 container, and the registry that service providers fill.
 *
 * A service is made once, by its factory, on first use; plugins are services
 * filed under their type and looked up together with `tagged()`. Registration
 * ends with `freeze()` at boot: the container is immutable after boot
 * (CONSTITUTION.md §3). Resolution refuses a dependency cycle.
 */
final class Container implements ContainerInterface, ServiceRegistry
{
    /**
     * The deepest chain of services resolving one another.
     */
    private const int MAX_DEPTH = 32;

    /**
     * Factories by service id.
     *
     * @var array<string, Closure(): object>
     */
    private array $factories = [];

    /**
     * Services made so far, by id.
     *
     * @var array<string, object>
     */
    private array $instances = [];

    /**
     * Service ids by plugin type, in tag order.
     *
     * @var array<string, list<string>>
     */
    private array $tags = [];

    /**
     * The ids being resolved, outermost first.
     *
     * @var list<string>
     */
    private array $resolving = [];

    /**
     * Whether registration has ended.
     */
    private bool $frozen = false;

    /**
     * @param string            $id      The service id, usually its class name.
     * @param Closure(): object $factory Makes the service; it captures what it needs.
     *
     * @throws ContainerException When the container is frozen.
     */
    #[Override]
    public function singleton(string $id, Closure $factory): void
    {
        $this->assertOpen($id);
        $this->factories[$id] = $factory;
    }

    /**
     * @param string $type The plugin type.
     * @param string $id   The service id.
     *
     * @throws ContainerException When the container is frozen.
     */
    #[Override]
    public function tag(string $type, string $id): void
    {
        $this->assertOpen($id);
        $this->tags[$type][] = $id;
    }

    /**
     * End registration.
     */
    public function freeze(): void
    {
        $this->frozen = true;
    }

    /**
     * @param string $id The service id.
     *
     * @return bool Whether a service is registered under it.
     */
    #[Override]
    public function has(string $id): bool
    {
        return array_key_exists($id, $this->factories);
    }

    /**
     * @param string $id The service id.
     *
     * @throws NotFoundException  When no service is registered under it.
     * @throws ContainerException When it cannot be made.
     *
     * @return mixed The service.
     */
    #[Override]
    public function get(string $id): mixed
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (!$this->has($id)) {
            throw new NotFoundException('No service is registered as ' . $id . '.');
        }

        $this->make($id);

        return $this->instances[$id];
    }

    /**
     * @param string $type The plugin type.
     *
     * @throws ContainerException When a plugin cannot be made.
     *
     * @return list<object> The plugins filed under it, in tag order.
     */
    public function tagged(string $type): array
    {
        $plugins = [];

        foreach ($this->tags[$type] ?? [] as $id) {
            $plugin = $this->get($id);

            if (!is_object($plugin)) {
                continue;
            }

            $plugins[] = $plugin;
        }

        return $plugins;
    }

    /**
     * Make a registered service and keep it, guarding against a cycle.
     *
     * @param string $id The service id.
     *
     * @throws ContainerException When the chain is a cycle, too deep, or a factory throws.
     */
    private function make(string $id): void
    {
        if (in_array($id, $this->resolving, true) || count($this->resolving) >= self::MAX_DEPTH) {
            throw new ContainerException('Cannot resolve ' . $id . ': ' . implode(' -> ', [...$this->resolving, $id]) . '.');
        }

        $this->resolving[] = $id;

        try {
            $service = $this->factories[$id]();
        } catch (Throwable $throwable) {
            throw new ContainerException('The factory for ' . $id . ' threw: ' . $throwable->getMessage(), 0, $throwable);
        } finally {
            $this->resolving = self::without($this->resolving, $id);
        }

        $this->instances[$id] = $service;
    }

    /**
     * @param string $id The id being registered.
     *
     * @throws ContainerException When the container is frozen.
     */
    private function assertOpen(string $id): void
    {
        if ($this->frozen) {
            throw new ContainerException('Cannot register ' . $id . ': the container is frozen.');
        }
    }

    /**
     * @param list<string> $ids The ids being resolved.
     * @param string       $id  The one that finished.
     *
     * @return list<string> The rest.
     */
    private static function without(array $ids, string $id): array
    {
        $rest = [];

        foreach ($ids as $resolving) {
            if ($resolving === $id) {
                continue;
            }

            $rest[] = $resolving;
        }

        return $rest;
    }
}
