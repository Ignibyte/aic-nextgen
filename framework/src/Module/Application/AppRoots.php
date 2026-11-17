<?php

declare(strict_types=1);

namespace AIC\Module\Application;

use AIC\HttpClient\Application\Outbound;
use AIC\Schedule\Domain\Schedule;

/**
 * The web side's roots the generated wiring reaches by path (#305), through
 * the context's one `roots()`: the modules' schedules and protected prefixes,
 * when the app booted and where it lives (`roots()->root()`), and the
 * outbound client and its kin (`roots()->outbound()->client()`). They sit
 * behind one accessor so the context stays within PHPMD's method count.
 */
final readonly class AppRoots
{
    /**
     * @param list<Schedule> $schedules         Every module's schedules.
     * @param list<string>   $protectedPrefixes Every module's protected path prefixes.
     * @param string         $bootedAt          When the app booted to serve this request, ISO 8601 in UTC.
     * @param string         $root              The app's root directory.
     * @param Outbound       $outbound          The client, pool, factories and upstreams for upstream calls.
     */
    public function __construct(
        private array $schedules,
        private array $protectedPrefixes,
        private string $bootedAt,
        private string $root,
        private Outbound $outbound,
    ) {
    }

    /**
     * @return list<Schedule> Every module's schedules.
     */
    public function schedules(): array
    {
        return $this->schedules;
    }

    /**
     * @return list<string> Every module's protected path prefixes.
     */
    public function protectedPrefixes(): array
    {
        return $this->protectedPrefixes;
    }

    /**
     * @return string When the app booted to serve this request, ISO 8601 in UTC.
     */
    public function bootedAt(): string
    {
        return $this->bootedAt;
    }

    /**
     * @return string The app's root directory.
     */
    public function root(): string
    {
        return $this->root;
    }

    /**
     * @return Outbound The client, pool, factories and upstreams for calls to upstream APIs.
     */
    public function outbound(): Outbound
    {
        return $this->outbound;
    }
}
