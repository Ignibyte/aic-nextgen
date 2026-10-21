<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Block\Contracts\Block;
use AIC\Container\Contracts\ServiceProvider;
use AIC\Container\Contracts\ServiceRegistry;
use Gates\Domain\ModuleDefinition;
use InvalidArgumentException;
use Override;

use function implode;
use function sprintf;
use function strlen;
use function strtr;

/**
 * Writes `App\Generated\ModuleProvider` (#92): every module's blocks and
 * plugins, each a singleton tagged under its type, in build order.
 */
final readonly class ProviderSource
{
    /**
     * The widest line PHPCS allows.
     */
    private const int LINE_LIMIT = 140;

    /**
     * A singleton too long for one line, `%1$s` standing for the class's short name.
     */
    private const string LONG_SINGLETON = <<<'PHP'
                $serviceRegistry->singleton(
                    %1$s::class,
                    static fn (): %1$s => new %1$s(),
                );
        PHP;

    /**
     * The class, `{registrations}` standing for the calls on the registry.
     */
    private const string TEMPLATE = <<<'PHP'
        final readonly class ModuleProvider implements ServiceProvider
        {
            /**
             * @param ServiceRegistry $serviceRegistry Where the services and plugins go.
             */
            #[Override]
            public function register(ServiceRegistry $serviceRegistry): void
            {
        {registrations}
            }
        }
        PHP;

    /**
     * @param PhpClassFile $phpClassFile Lays out the file.
     */
    public function __construct(
        private PhpClassFile $phpClassFile = new PhpClassFile(),
    ) {
    }

    /**
     * @param list<ModuleDefinition> $modules The modules in build order.
     *
     * @throws InvalidArgumentException When no module has a block or a plugin, which would leave `register()`'s parameter unread.
     *
     * @return string The class file.
     */
    public function source(array $modules): string
    {
        $classes = [ServiceProvider::class, ServiceRegistry::class, Override::class];
        $lines = [];

        foreach ($modules as $module) {
            foreach ($this->plugins($module) as [$type, $class]) {
                $classes = [...$classes, $type, $class];
                $lines = [...$lines, ...$this->registration($type, $class)];
            }
        }

        if ($lines === []) {
            throw new InvalidArgumentException(
                'no module declares a block or a plugin; the generated ModuleProvider needs at least one (#92).',
            );
        }

        $docblock = $this->phpClassFile->generated('the module manifests', "the modules' blocks and plugins, after App\\AppProvider's.");
        $body = strtr(self::TEMPLATE, ['{registrations}' => implode("\n", $lines)]);

        return $this->phpClassFile->file('App\\Generated', $classes, [], $docblock, $body);
    }

    /**
     * @param ModuleDefinition $moduleDefinition A module.
     *
     * @return list<array{string, string}> Its blocks and plugins, each as its type and its class.
     */
    private function plugins(ModuleDefinition $moduleDefinition): array
    {
        $plugins = [];

        foreach ($moduleDefinition->module->blocks() as $block) {
            $plugins[] = [Block::class, $block];
        }

        foreach ($moduleDefinition->module->plugins() as $pluginTag) {
            $plugins[] = [$pluginTag->type, $pluginTag->class];
        }

        return $plugins;
    }

    /**
     * @param string $type  A plugin type.
     * @param string $class A plugin.
     *
     * @return list<string> Its singleton and its tag.
     */
    private function registration(string $type, string $class): array
    {
        $short = $this->phpClassFile->short($class);
        $singleton = sprintf('        $serviceRegistry->singleton(%1$s::class, static fn (): %1$s => new %1$s());', $short);

        if (strlen($singleton) > self::LINE_LIMIT) {
            $singleton = sprintf(self::LONG_SINGLETON, $short);
        }

        $tag = sprintf('        $serviceRegistry->tag(%s::class, %s::class);', $this->phpClassFile->short($type), $short);

        return [$singleton, $tag];
    }
}
