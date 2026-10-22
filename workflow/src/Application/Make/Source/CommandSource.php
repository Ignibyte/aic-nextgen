<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make\Source;

use function strtr;

/**
 * A command (`make:command`): one line, exit 0 (#122).
 */
final readonly class CommandSource
{
    /**
     * The file, each `{name}` standing for the value of the parameter it names.
     */
    private const string TEMPLATE = <<<'PHP'
        <?php

        declare(strict_types=1);

        namespace {namespace}\Command;

        use AIC\Controller\Contracts\Command;
        use AIC\Controller\Contracts\CommandInput;
        use AIC\Controller\Contracts\CommandResponse;
        use AIC\Controller\Domain\CommandResult;
        use Override;

        /**
         * `aic {name}`, made by bin/aic make:command (#122): one line.
         */
        final readonly class {class} implements Command
        {
            /**
             * @param CommandInput $commandInput The arguments.
             *
             * @return CommandResponse Its line, exit 0.
             */
            #[Override]
            public function command(CommandInput $commandInput): CommandResponse
            {
                return new CommandResult(['{name}'], 0);
            }
        }
        PHP;

    /**
     * @param string $namespace The module's namespace.
     * @param string $class     The command.
     * @param string $name      Its name.
     *
     * @return string The file's bytes.
     */
    public function source(string $namespace, string $class, string $name): string
    {
        return strtr(self::TEMPLATE, ['{namespace}' => $namespace, '{class}' => $class, '{name}' => $name]) . "\n";
    }
}
