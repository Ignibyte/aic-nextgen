<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Block\Contracts\Block;
use AIC\Container\Contracts\ServiceRegistry;
use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\Job;
use AIC\Module\Application\BlockWiring;
use AIC\Module\Application\CommandWiring;
use AIC\Module\Application\ConsoleContext;
use AIC\Module\Application\HandlerContext;
use AIC\Module\Application\HandlerWiring;
use AIC\Module\Application\JobWiring;
use AIC\Module\Application\MiddlewareWiring;
use AIC\Module\Application\PluginWiring;
use AIC\Module\Application\RootlessBlockWiring;
use AIC\Module\Application\RootlessCommandWiring;
use AIC\Module\Application\RootlessJobWiring;
use AIC\Module\Application\RootlessMiddlewareWiring;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The optional parts of the two generated facades (#93), apart from
 * `FacadeSource` so it stays one class: each optional interface's method by
 * the aggregator it calls, and the context-free sibling of each interface
 * whose aggregator may read no root.
 */
final readonly class FacadeParts
{
    /**
     * Each optional web interface's method, by the aggregator it calls.
     */
    public const array WEB = [
        'ModuleBlocks' => [BlockWiring::class, <<<'PHP'
                /**
                 * @param HandlerContext $handlerContext The roots.
                 *
                 * @return list<Block> The modules' blocks, in build order.
                 */
                #[Override]
                public function blocks(HandlerContext $handlerContext): array
                {
                    return (new ModuleBlocks($handlerContext))->all();
                }
            PHP, [HandlerContext::class, Block::class]],
        'ModuleHandlers' => [HandlerWiring::class, <<<'PHP'
                /**
                 * @param HandlerContext $handlerContext The roots and the kind wrappers.
                 *
                 * @return array<string, RequestHandlerInterface> The modules' handlers by id.
                 */
                #[Override]
                public function handlers(HandlerContext $handlerContext): array
                {
                    return (new ModuleHandlers($handlerContext))->byId();
                }
            PHP, [HandlerContext::class, RequestHandlerInterface::class]],
        'ModuleMiddleware' => [MiddlewareWiring::class, <<<'PHP'
                /**
                 * @param HandlerContext $handlerContext The roots.
                 *
                 * @return list<MiddlewareInterface> The modules' middleware after the session, in build order.
                 */
                #[Override]
                public function afterSession(HandlerContext $handlerContext): array
                {
                    return (new ModuleMiddleware($handlerContext))->afterSession();
                }
            PHP, [HandlerContext::class, MiddlewareInterface::class]],
        'ModuleProvider' => [PluginWiring::class, <<<'PHP'
                /**
                 * @param ServiceRegistry $serviceRegistry Where the modules' plugins go.
                 */
                #[Override]
                public function register(ServiceRegistry $serviceRegistry): void
                {
                    (new ModuleProvider())->register($serviceRegistry);
                }
            PHP, [ServiceRegistry::class]],
    ];

    /**
     * Each optional console interface's method, by the aggregator it calls.
     */
    public const array CONSOLE = [
        'ModuleCommands' => [CommandWiring::class, <<<'PHP'
                /**
                 * @param ConsoleContext $consoleContext The console's roots.
                 *
                 * @return array<string, Command> The modules' commands by name.
                 */
                #[Override]
                public function commands(ConsoleContext $consoleContext): array
                {
                    return (new ModuleCommands($consoleContext))->byName();
                }
            PHP, [ConsoleContext::class, Command::class]],
        'ModuleJobs' => [JobWiring::class, <<<'PHP'
                /**
                 * @param ConsoleContext $consoleContext The console's roots.
                 *
                 * @return array<string, Job> The modules' jobs by name.
                 */
                #[Override]
                public function jobs(ConsoleContext $consoleContext): array
                {
                    return (new ModuleJobs($consoleContext))->byName();
                }
            PHP, [ConsoleContext::class, Job::class]],
        'ModuleProvider' => [PluginWiring::class, <<<'PHP'
                /**
                 * @param ServiceRegistry $serviceRegistry Where the modules' plugins go.
                 */
                #[Override]
                public function register(ServiceRegistry $serviceRegistry): void
                {
                    (new ModuleProvider())->register($serviceRegistry);
                }
            PHP, [ServiceRegistry::class]],
    ];

    /**
     * The context-free sibling of each optional interface whose aggregator may read no root (#93).
     */
    public const array ROOTLESS = [
        BlockWiring::class => RootlessBlockWiring::class,
        CommandWiring::class => RootlessCommandWiring::class,
        JobWiring::class => RootlessJobWiring::class,
        MiddlewareWiring::class => RootlessMiddlewareWiring::class,
    ];
}
