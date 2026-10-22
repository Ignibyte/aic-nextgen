<?php

declare(strict_types=1);

namespace Gates\Domain;

use AIC\Block\Contracts\Block;
use AIC\Controller\Contracts\Api;
use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\Job;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\Stream;

use function array_any;
use function ltrim;

/**
 * The five kinds' methods (#56), and a block's `component()` (#58). Each
 * interface dictates its method's one parameter, so a class that does not
 * need it cannot drop it; gate:10 and gate:11 let such a method leave it
 * unread, and nothing else.
 */
final readonly class KindMethods
{
    /**
     * Each interface and the method it declares.
     */
    private const array METHODS = [
        Api::class => 'api',
        Block::class => 'component',
        Command::class => 'command',
        Job::class => 'run',
        Page::class => 'page',
        Stream::class => 'stream',
    ];

    /**
     * @param string $contract A kind's interface.
     *
     * @return string The one method it declares for the kind; empty when it declares none or several (#109).
     */
    public function methodOf(string $contract): string
    {
        return self::METHODS[$contract] ?? '';
    }

    /**
     * @param list<string> $interfaces The full names of the interfaces a class implements.
     * @param string       $method     A method of that class.
     *
     * @return bool Whether the method is one of those interfaces' kind method.
     */
    public function isKindMethod(array $interfaces, string $method): bool
    {
        return array_any($interfaces, static fn (string $interface): bool => (self::METHODS[ltrim($interface, '\\')] ?? '') === $method);
    }
}
