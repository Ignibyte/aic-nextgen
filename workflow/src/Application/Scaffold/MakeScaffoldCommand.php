<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Scaffold;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Workflow\Application\Console\ConsoleArguments;
use AIC\Workflow\Application\Make\MakeRun;
use AIC\Workflow\Application\RunStore;
use AIC\Workflow\Domain\Console\CommandUsage;
use AIC\Workflow\Domain\Console\ParsedArguments;
use AIC\Workflow\Domain\Make\MakeRecipe;
use AIC\Workflow\Exception\WorkflowRefusal;
use InvalidArgumentException;
use Override;

use function in_array;
use function preg_match;

/**
 * `bin/aic make:scaffold <kind> --from=<kind> [--dry-run] [--json]` (#299):
 * the one way an agent gains a kind the app lacks. It writes
 * `scaffolds/<kind>/` from a kind that exists, and only in a run whose
 * operator approved that kind: the agent asks (`workflow:ask`), and the
 * operator answers `scaffold <kind>`. It writes through the run every make
 * uses, so the guard's wall judges each file and `code.made` records it.
 */
final readonly class MakeScaffoldCommand implements Command
{
    /**
     * @param MakeRun           $makeRun           The run.
     * @param ScaffoldLoader    $scaffoldLoader    The kinds there are.
     * @param ScaffoldCopy      $scaffoldCopy      The new kind's files.
     * @param RunStore          $runStore          The open run.
     * @param ScaffoldApprovals $scaffoldApprovals What it approves.
     */
    public function __construct(
        private MakeRun $makeRun,
        private ScaffoldLoader $scaffoldLoader,
        private ScaffoldCopy $scaffoldCopy,
        private RunStore $runStore,
        private ScaffoldApprovals $scaffoldApprovals = new ScaffoldApprovals(),
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
        return $this->makeRun->run(
            $commandInput,
            new CommandUsage('make:scaffold', 'make:scaffold <kind> --from=<kind> [--dry-run] [--json]', ['dry-run'], ['from'], 1),
            $this->recipe(...),
        );
    }

    /**
     * @param ParsedArguments $parsedArguments The line.
     *
     * @throws WorkflowRefusal When the kind breaks the rule, is reserved or exists, the run did not approve it, or the
     *                         source is no kind.
     *
     * @return MakeRecipe The new folder's files.
     */
    private function recipe(ParsedArguments $parsedArguments): MakeRecipe
    {
        $kind = $parsedArguments->positional(0);
        $from = $parsedArguments->flag('from');

        if (preg_match('/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/', $kind) !== 1 || $from === '') {
            throw new WorkflowRefusal(
                'make:scaffold takes a kind, lower case words joined by -, and --from=<kind>.',
                ConsoleArguments::USAGE,
            );
        }

        $this->free($kind);

        if (!in_array($kind, $this->scaffoldApprovals->kinds($this->runStore->load()), true)) {
            throw new WorkflowRefusal(
                'make:scaffold writes scaffolds/' . $kind . '/ only in a run the operator approved: ask with bin/aic workflow:ask, '
                    . 'and the operator answers "scaffold ' . $kind . '".',
            );
        }

        return new MakeRecipe('', 'scaffold ' . $kind, $this->scaffoldCopy->files($kind, $from), null, [], lines: [
            'then: describe it, set each path, arguments, edits and probe values; bin/aic make:catalog says what is missing.',
        ]);
    }

    /**
     * @param string $kind The new kind.
     *
     * @throws WorkflowRefusal When a built-in make or a scaffold has it.
     */
    private function free(string $kind): void
    {
        if (in_array($kind, ScaffoldLoader::RESERVED, true) || in_array($kind, $this->scaffoldLoader->kinds(), true)) {
            throw new WorkflowRefusal('the kind ' . $kind . ' is there already: bin/aic make:catalog lists every kind.');
        }
    }
}
