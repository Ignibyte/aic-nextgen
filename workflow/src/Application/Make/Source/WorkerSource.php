<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make\Source;

use AIC\Workflow\Application\Make\MakeTemplates;
use AIC\Workflow\Exception\WorkflowRefusal;

use function str_replace;

/**
 * An interpreted worker's files (`make:worker`, #335): its Composer manifest
 * and its README.
 */
final readonly class WorkerSource
{
    /**
     * @param MakeTemplates $makeTemplates The built-in templates (#301).
     */
    public function __construct(
        private MakeTemplates $makeTemplates = new MakeTemplates(),
    ) {
    }

    /**
     * @param string $worker    The worker's name.
     * @param string $namespace Its namespace, without the last `\`.
     *
     * @throws WorkflowRefusal When its template is missing.
     *
     * @return string Its composer.json.
     */
    public function manifest(string $worker, string $namespace): string
    {
        return $this->makeTemplates->render(
            'worker',
            'composer.json.tpl',
            ['worker' => $worker, 'psr4' => str_replace('\\', '\\\\', $namespace . '\\')],
        );
    }

    /**
     * @param string $worker    The worker's name.
     * @param string $namespace Its namespace, without the last `\`.
     *
     * @throws WorkflowRefusal When its template is missing.
     *
     * @return string Its README.md.
     */
    public function readme(string $worker, string $namespace): string
    {
        return $this->makeTemplates->render('worker', 'README.md.tpl', ['worker' => $worker, 'namespace' => $namespace]);
    }
}
