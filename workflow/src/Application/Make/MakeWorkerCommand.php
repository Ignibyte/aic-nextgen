<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Workflow\Application\Make\Source\WorkerSource;
use AIC\Workflow\Application\ProjectFiles;
use AIC\Workflow\Domain\Console\CommandUsage;
use AIC\Workflow\Domain\Console\ParsedArguments;
use AIC\Workflow\Domain\Make\MadeFile;
use AIC\Workflow\Domain\Make\MakeRecipe;
use AIC\Workflow\Exception\WorkflowRefusal;
use InvalidArgumentException;
use Override;

use function str_contains;

/**
 * `bin/aic make:worker <worker> [--dry-run] [--json]` (#335): an interpreted
 * worker under `workers/<worker>/`, a Composer project of its own for a
 * library that does not compile. It prints the root's composer.json and
 * phpcs.xml.dist lines that let every static gate read it.
 */
final readonly class MakeWorkerCommand implements Command
{
    /**
     * @param MakeRun      $makeRun      The run.
     * @param ProjectFiles $projectFiles The root's composer.json and phpcs.xml.dist.
     * @param WorkerNames  $workerNames  The worker's names.
     * @param WorkerSource $workerSource Writes its files.
     */
    public function __construct(
        private MakeRun $makeRun,
        private ProjectFiles $projectFiles,
        private WorkerNames $workerNames = new WorkerNames(),
        private WorkerSource $workerSource = new WorkerSource(),
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
            new CommandUsage('make:worker', 'make:worker <worker> [--dry-run] [--json]', ['dry-run'], [], 1),
            $this->recipe(...),
        );
    }

    /**
     * @param ParsedArguments $parsedArguments The line.
     *
     * @throws WorkflowRefusal When the name breaks the rule.
     *
     * @return MakeRecipe The manifest and the README.
     */
    private function recipe(ParsedArguments $parsedArguments): MakeRecipe
    {
        $worker = $parsedArguments->positional(0);
        $namespace = $this->workerNames->namespace($worker);
        $folder = $this->workerNames->folder($worker);
        $files = [
            new MadeFile($folder . 'composer.json', $this->workerSource->manifest($worker, $namespace)),
            new MadeFile($folder . 'README.md', $this->workerSource->readme($worker, $namespace)),
        ];

        return new MakeRecipe('', $worker, $files, null, lines: $this->lines($worker, $namespace));
    }

    /**
     * @param string $worker    The worker.
     * @param string $namespace Its namespace.
     *
     * @throws WorkflowRefusal When composer.json or phpcs.xml.dist cannot be read.
     *
     * @return list<string> The root lines it lacks.
     */
    private function lines(string $worker, string $namespace): array
    {
        $autoload = $this->workerNames->autoload($worker, $namespace);
        $root = $this->workerNames->phpcsRoot($worker, $namespace);
        $composer = str_contains($this->projectFiles->read('composer.json') ?? '', $autoload)
            ? []
            : ["once src/ holds a class, add to composer.json's autoload-dev.psr-4 and run composer dump-autoload: " . $autoload];
        $phpcs = str_contains($this->projectFiles->read('phpcs.xml.dist') ?? '', $root)
            ? []
            : ["add to phpcs.xml.dist's rootNamespaces: " . $root];

        return [...$composer, ...$phpcs];
    }
}
