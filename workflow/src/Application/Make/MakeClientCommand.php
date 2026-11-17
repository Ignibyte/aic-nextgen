<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Workflow\Application\Make\Source\ClientSource;
use AIC\Workflow\Domain\Console\CommandUsage;
use AIC\Workflow\Domain\Console\ParsedArguments;
use AIC\Workflow\Domain\Make\MadeFile;
use AIC\Workflow\Domain\Make\MakeRecipe;
use AIC\Workflow\Exception\WorkflowRefusal;
use InvalidArgumentException;
use Override;

use function sprintf;
use function strtoupper;

/**
 * `bin/aic make:client <module> <Name> [--dry-run] [--json]` (#305): a typed
 * client of one upstream API, `src/Service/<Name>Client.php`, and the reply
 * value its calls answer with, `src/Service/<Name>Reply.php`. The client
 * takes the outbound roots, so the manifest gains nothing; the upstream's
 * URL and token go in config/app.php, which the make prints and leaves to the
 * agent.
 */
final readonly class MakeClientCommand implements Command
{
    /**
     * What `bin/aic make:client` takes.
     */
    private const string USAGE = 'make:client <module> <Name> [--dry-run] [--json]';

    /**
     * @param MakeRun      $makeRun      The run.
     * @param MakeNames    $makeNames    The rules.
     * @param ClientSource $clientSource Writes the client and its reply.
     */
    public function __construct(
        private MakeRun $makeRun,
        private MakeNames $makeNames = new MakeNames(),
        private ClientSource $clientSource = new ClientSource(),
    ) {
    }

    /**
     * @param CommandInput $commandInput The line.
     *
     * @throws InvalidArgumentException When the response exit code is outside 0 to 255.
     *
     * @return CommandResponse What it wrote.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        return $this->makeRun->run($commandInput, $this->usage(), $this->recipe(...));
    }

    /**
     * @return CommandUsage The line it takes: a module and a name, and the dry-run flag.
     */
    private function usage(): CommandUsage
    {
        return new CommandUsage('make:client', self::USAGE, ['dry-run'], [], 2);
    }

    /**
     * @param ParsedArguments $parsedArguments The line.
     *
     * @throws WorkflowRefusal When a name breaks a rule.
     *
     * @return MakeRecipe The client, its reply, and the config line to add.
     */
    private function recipe(ParsedArguments $parsedArguments): MakeRecipe
    {
        [$module, $namespace, $name] = $this->makeNames->target('', $parsedArguments);
        $upstream = $this->makeNames->kebab($name);
        [$client, $reply] = $this->clientSource->sources($namespace, $name, $upstream);
        $folder = 'modules/' . $module . '/src/Service/';
        $variable = 'AIC_UPSTREAM_' . strtoupper($this->makeNames->snake($name));
        $line = sprintf(
            "Add the upstream to config/app.php's upstreams: '%s' => new Upstream(%s, %s).",
            $upstream,
            "\$setting('" . $variable . "', 'https://...')",
            "\$setting('" . $variable . "_TOKEN', '')",
        );

        return new MakeRecipe(
            $module,
            $name . 'Client',
            [new MadeFile($folder . $name . 'Client.php', $client), new MadeFile($folder . $name . 'Reply.php', $reply)],
            null,
            lines: [$line],
        );
    }
}
