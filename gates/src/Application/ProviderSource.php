<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Block\Contracts\Block;
use AIC\Container\Contracts\ServiceProvider;
use AIC\Container\Contracts\ServiceRegistry;
use AIC\Module\Contracts\ModuleSection;
use AIC\Module\Contracts\PluginTag;
use Gates\Domain\ModuleDefinition;
use InvalidArgumentException;
use Override;

use function count;
use function explode;
use function implode;
use function sprintf;
use function strlen;
use function strtr;

/**
 * Writes the generated providers (#92): one `<M>Provider` per module with
 * blocks or plugins, each a singleton tagged under its type, and
 * `App\Generated\ModuleProvider`, which calls each module's in build order, in
 * chained parts (`ChainSource`).
 */
final readonly class ProviderSource
{
    /**
     * The widest line PHPCS allows.
     */
    private const int LINE_LIMIT = 140;

    /**
     * The most lines a `register()` body may take (PHPCS's 20-line function limit).
     */
    private const int MAX_LINES = 20;

    /**
     * The most modules one part calls: its three fixed types and the next part leave 11 of PHPMD's 15.
     */
    private const int PART_SIZE = 11;

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
     * A provider, `{class}`, `{what}` and `{body}` standing for its name, what it registers and the calls.
     */
    private const string TEMPLATE = <<<'PHP'
        final readonly class {class} implements ServiceProvider
        {
            /**
             * @param ServiceRegistry $serviceRegistry Where {what} go.
             */
            #[Override]
            public function register(ServiceRegistry $serviceRegistry): void
            {
        {body}
            }
        }
        PHP;

    /**
     * @param PhpClassFile $phpClassFile Lays out each file.
     * @param ChainSource  $chainSource  Splits the aggregator into parts.
     */
    public function __construct(
        private PhpClassFile $phpClassFile = new PhpClassFile(),
        private ChainSource $chainSource = new ChainSource(),
    ) {
    }

    /**
     * @param ModuleDefinition $moduleDefinition A module with blocks or plugins.
     *
     * @throws InvalidArgumentException When its class would not fit the gates.
     *
     * @return string Its `<M>Provider` class file.
     */
    public function module(ModuleDefinition $moduleDefinition): string
    {
        $classes = [ServiceProvider::class, ServiceRegistry::class, Override::class];
        $lines = [];

        foreach ($this->plugins($moduleDefinition) as [$type, $class]) {
            $classes = [...$classes, $type, $class];
            $lines = [...$lines, ...$this->registration($type, $class)];
        }

        $body = implode("\n", $lines);

        if (count(explode("\n", $body)) > self::MAX_LINES) {
            throw new InvalidArgumentException(
                sprintf('module %s: its blocks and plugins do not fit one generated class; split the module.', $moduleDefinition->name),
            );
        }

        $from = 'modules/' . $moduleDefinition->name . '/' . $moduleDefinition->name . '.module.php';
        $source = strtr(self::TEMPLATE, [
            '{body}' => $body,
            '{class}' => $moduleDefinition->studlyName() . 'Provider',
            '{what}' => "the module's blocks and plugins",
        ]);
        $docblock = $this->phpClassFile->generated($from, 'its blocks and plugins.');

        return $this->phpClassFile->file('App\\Generated', $classes, [], $docblock, $source);
    }

    /**
     * @param list<string> $moduleClasses The modules' `<M>Provider` classes, in build order.
     *
     * @throws InvalidArgumentException When there are none, which would leave `register()`'s parameter unread.
     *
     * @return array<string, string> `ModuleProvider` and its parts, by file name.
     */
    public function aggregate(array $moduleClasses): array
    {
        if ($moduleClasses === []) {
            throw new InvalidArgumentException(
                'no module declares a block or a plugin; the generated ModuleProvider needs at least one (#92).',
            );
        }

        $files = [];
        $docblock = $this->phpClassFile->generated('the module manifests', "the modules' blocks and plugins, after App\\AppProvider's.");

        foreach ($this->chainSource->parts('ModuleProvider', $moduleClasses, self::PART_SIZE, self::PART_SIZE) as $part) {
            $files[$part['class'] . '.php'] = $this->part($part, $docblock);
        }

        return $files;
    }

    /**
     * @param array{class: string, members: list<string>, next: string} $part     A part of `ModuleProvider`.
     * @param string                                                    $docblock Its class docblock.
     *
     * @throws InvalidArgumentException Never: each part fits by its size.
     *
     * @return string Its class file: each module's provider called in turn, then the next part's.
     */
    private function part(array $part, string $docblock): string
    {
        $calls = $this->chainSource->calls($part);
        $lines = [];

        foreach ($calls as $call) {
            $lines[] = '        (new ' . $call . '())->register($serviceRegistry);';
        }

        $source = strtr(self::TEMPLATE, [
            '{body}' => implode("\n", $lines),
            '{class}' => $part['class'],
            '{what}' => "the modules' blocks and plugins",
        ]);
        $classes = [ServiceProvider::class, ServiceRegistry::class, Override::class, ...$this->chainSource->qualified($calls)];

        return $this->phpClassFile->file('App\\Generated', $classes, [], $docblock, $source);
    }

    /**
     * @param ModuleDefinition $moduleDefinition A module.
     *
     * @return list<array{string, string}> Its blocks and plugins, each as its type and its class.
     */
    private function plugins(ModuleDefinition $moduleDefinition): array
    {
        $plugins = [];

        foreach ($moduleDefinition->entries(ModuleSection::Blocks) as $block) {
            $plugins[] = [Block::class, $block];
        }

        foreach ($moduleDefinition->declared(PluginTag::class) as $pluginTag) {
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
