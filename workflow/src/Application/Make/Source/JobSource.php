<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make\Source;

use function strtr;

/**
 * A job (`make:job`): done at once (#122).
 */
final readonly class JobSource
{
    /**
     * The file, each `{name}` standing for the value of the parameter it names.
     */
    private const string TEMPLATE = <<<'PHP'
        <?php

        declare(strict_types=1);

        namespace {namespace}\Job;

        use AIC\Controller\Contracts\Job;
        use AIC\Controller\Contracts\JobInput;
        use AIC\Controller\Contracts\JobResponse;
        use AIC\Controller\Domain\JobDone;
        use Override;

        /**
         * The `{name}` job, made by bin/aic make:job (#122): done at once.
         */
        final readonly class {class} implements Job
        {
            /**
             * @param JobInput $jobInput The payload.
             *
             * @return JobResponse Done.
             */
            #[Override]
            public function run(JobInput $jobInput): JobResponse
            {
                return new JobDone();
            }
        }
        PHP;

    /**
     * @param string $namespace The module's namespace.
     * @param string $class     The job.
     * @param string $name      Its name.
     *
     * @return string The file's bytes.
     */
    public function source(string $namespace, string $class, string $name): string
    {
        return strtr(self::TEMPLATE, ['{namespace}' => $namespace, '{class}' => $class, '{name}' => $name]) . "\n";
    }
}
