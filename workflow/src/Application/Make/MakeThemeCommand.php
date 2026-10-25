<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Workflow\Application\Console\ConsoleArguments;
use AIC\Workflow\Domain\Console\CommandUsage;
use AIC\Workflow\Domain\Console\ParsedArguments;
use AIC\Workflow\Domain\Make\MakeRecipe;
use AIC\Workflow\Exception\WorkflowRefusal;
use InvalidArgumentException;
use Override;

use function strlen;

/** Make the first selected site theme, its typed regions and usable exported layout service. */
final readonly class MakeThemeCommand implements Command
{
    /**
     * @param MakeRun    $makeRun    The shared guarded run.
     * @param MakeThemes $makeThemes The theme's files and the site's layout service.
     * @param MakeNames  $makeNames  Portable names.
     * @param MakeCalls  $makeCalls  The layout service's export.
     */
    public function __construct(
        private MakeRun $makeRun,
        private MakeThemes $makeThemes = new MakeThemes(),
        private MakeNames $makeNames = new MakeNames(),
        private MakeCalls $makeCalls = new MakeCalls(),
    ) {
    }

    /**
     * @param CommandInput $commandInput The theme name and flags.
     *
     * @throws InvalidArgumentException When the response exit code is invalid.
     *
     * @return CommandResponse The complete preview, successful make or nonwriting refusal.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        return $this->makeRun->run(
            $commandInput,
            new CommandUsage('make:theme', 'make:theme <name> [--dry-run] [--json]', ['dry-run'], [], 1),
            $this->recipe(...),
        );
    }

    /**
     * @param ParsedArguments $parsedArguments The parsed command.
     *
     * @throws WorkflowRefusal When the name is invalid or exceeds the bounded scaffold size.
     *
     * @return MakeRecipe The theme, the site's layout service and its export, and the lines to add by hand.
     */
    private function recipe(ParsedArguments $parsedArguments): MakeRecipe
    {
        $name = $this->makeNames->word($parsedArguments->positional(0), 'theme');

        if (strlen($name) > 32) {
            throw new WorkflowRefusal(
                'The theme name must be at most 32 bytes for portable generated identities.',
                ConsoleArguments::USAGE,
            );
        }

        return new MakeRecipe(
            'site',
            $name,
            $this->makeThemes->files($name),
            $this->makeCalls->classOnly('export', 'Service', 'HtmlLayout', 'App\\Site'),
            lines: [
                "set theme: '" . $name . "' in config/app.php",
                'add to composer.json\'s autoload.psr-4: "App\\\\Theme\\\\Components\\\\": "themes/' . $name . '/components/"',
                'add to composer.json\'s autoload.psr-4: "App\\\\Theme\\\\Layouts\\\\": "themes/' . $name . '/layouts/"',
                'add to phpcs.xml.dist\'s rootNamespaces: <element key="themes/' . $name . '/components" value="App\\Theme\\Components"/>',
                'add to phpcs.xml.dist\'s rootNamespaces: <element key="themes/' . $name . '/layouts" value="App\\Theme\\Layouts"/>',
            ],
        );
    }
}
