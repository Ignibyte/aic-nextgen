<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Workflow\Exception\WorkflowRefusal;

use function str_replace;
use function substr;

/**
 * An interpreted worker's names (#335): its namespace `Worker\<Studly>`, its
 * folder under `workers/`, and the root's autoload-dev and PHPCS lines that
 * let every static gate read it. A worker's name follows a module's rule.
 */
final readonly class WorkerNames
{
    /**
     * @param MakeNames $makeNames The module rule a worker's name follows.
     */
    public function __construct(
        private MakeNames $makeNames = new MakeNames(),
    ) {
    }

    /**
     * @param string $worker A worker's name.
     *
     * @throws WorkflowRefusal When it breaks the rule.
     *
     * @return string Its namespace, without the last `\`: `Worker\<Studly>`.
     */
    public function namespace(string $worker): string
    {
        return 'Worker' . substr($this->makeNames->module($worker), 3);
    }

    /**
     * @param string $worker A worker's name.
     *
     * @return string Its folder, relative to the root, ending in `/`.
     */
    public function folder(string $worker): string
    {
        return 'workers/' . $worker . '/';
    }

    /**
     * @param string $worker    A worker's name.
     * @param string $namespace Its namespace, without the last `\`.
     *
     * @return string Its line in the root composer.json's autoload-dev.psr-4.
     */
    public function autoload(string $worker, string $namespace): string
    {
        return '"' . str_replace('\\', '\\\\', $namespace . '\\') . '": "workers/' . $worker . '/src/"';
    }

    /**
     * @param string $worker    A worker's name.
     * @param string $namespace Its namespace, without the last `\`.
     *
     * @return string Its root in phpcs.xml.dist.
     */
    public function phpcsRoot(string $worker, string $namespace): string
    {
        return '<element key="workers/' . $worker . '/src" value="' . $namespace . '"/>';
    }
}
