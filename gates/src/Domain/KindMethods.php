<?php

declare(strict_types=1);

namespace Gates\Domain;

use AIC\Block\Contracts\Block;
use AIC\Controller\Contracts\Api;
use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\Job;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\Stream;
use AIC\Mcp\Domain\McpSteppedCall;
use AIC\Mcp\Domain\McpToolCall;
use AIC\Queue\Domain\WorkerJobs;

use function array_any;
use function in_array;
use function ltrim;

/**
 * The five kinds' methods (#56), a block's `component()` (#58), and an MCP
 * tool adapter's `callTool()` (#316), which every generated adapter declares with
 * the caller whether or not its tool takes one, as a stepped adapter's `step()`
 * does (#333). Each interface dictates its
 * method's parameters, so a class that does not need one cannot drop it;
 * gate:10 and gate:11 let such a method leave it unread, and nothing else.
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
        McpToolCall::class => 'callTool',
        Page::class => 'page',
        Stream::class => 'stream',
    ];

    /**
     * The methods an interface dictates that are not one kind's method (#333): a worker's generated jobs take the
     * database and the root whether or not a job asks for them (#336).
     */
    private const array DICTATED = [
        McpSteppedCall::class => ['callTool', 'step'],
        WorkerJobs::class => ['byName'],
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
     * @return bool Whether the method is one of those interfaces' kind method, or one they dictate (#333).
     */
    public function isKindMethod(array $interfaces, string $method): bool
    {
        return array_any($interfaces, static fn (string $interface): bool => (self::METHODS[ltrim($interface, '\\')] ?? '') === $method
            || in_array($method, self::DICTATED[ltrim($interface, '\\')] ?? [], true));
    }
}
