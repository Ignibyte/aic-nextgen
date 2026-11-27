<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Workflow\Application\Make\Source\WorkerSource;
use AIC\Workflow\Application\ProjectFiles;
use AIC\Workflow\Domain\Console\ParsedArguments;
use AIC\Workflow\Domain\Make\MadeFile;
use AIC\Workflow\Domain\Make\MakeRecipe;
use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * `make:job <worker> <Class> --worker` (#336): a job in an interpreted
 * worker's `src/Job/`, and with the worker's first job its runner,
 * `bin/aic.php`. It takes no name and edits no manifest, since a worker has
 * none: the build names the job by its class. The worker must be there.
 */
final readonly class WorkerJobRecipe
{
    /**
     * @param ProjectFiles $projectFiles Whether the worker is there.
     * @param MakeNames    $makeNames    The class rule.
     * @param WorkerNames  $workerNames  The worker's names.
     * @param WorkerSource $workerSource Writes the job.
     */
    public function __construct(
        private ProjectFiles $projectFiles,
        private MakeNames $makeNames = new MakeNames(),
        private WorkerNames $workerNames = new WorkerNames(),
        private WorkerSource $workerSource = new WorkerSource(),
    ) {
    }

    /**
     * @param ParsedArguments $parsedArguments The line: the worker and the class.
     *
     * @throws WorkflowRefusal When a name breaks a rule, a job name is given, or the worker is not there.
     *
     * @return MakeRecipe The job.
     */
    public function recipe(ParsedArguments $parsedArguments): MakeRecipe
    {
        $worker = $parsedArguments->positional(0);
        $namespace = $this->workerNames->namespace($worker);
        $class = $this->makeNames->class($parsedArguments->positional(1), '');
        $folder = $this->workerNames->folder($worker);

        if ($parsedArguments->positional(2) !== '') {
            throw new WorkflowRefusal("a worker's job takes no name: a worker has no manifest to declare it in.");
        }

        if ($this->projectFiles->read($folder . 'composer.json') === null) {
            throw new WorkflowRefusal('there is no worker ' . $worker . '; make it with bin/aic make:worker ' . $worker . '.');
        }

        $files = [new MadeFile($folder . 'src/Job/' . $class . '.php', $this->workerSource->job($worker, $namespace, $class))];

        if ($this->projectFiles->read($folder . 'bin/aic.php') === null) {
            $files[] = new MadeFile($folder . 'bin/aic.php', $this->workerSource->runner($worker, $namespace));
        }

        return new MakeRecipe('', $class, $files, null);
    }
}
