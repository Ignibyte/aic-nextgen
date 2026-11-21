<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make\Source;

use AIC\Workflow\Application\Make\MakeTemplates;
use AIC\Workflow\Domain\Make\MadeFile;
use AIC\Workflow\Exception\WorkflowRefusal;

use function lcfirst;

/**
 * Renders what `make:webhook` writes (#321): the POST Api in `src/Controller/`,
 * the delivery's value in `src/Service/` and the job it queues in `src/Job/`.
 */
final readonly class WebhookSource
{
    /**
     * @param MakeTemplates $makeTemplates Renders the webhook's `*.tpl` files.
     */
    public function __construct(
        private MakeTemplates $makeTemplates = new MakeTemplates(),
    ) {
    }

    /**
     * @param string $folder    The module's `src/`, relative to the project.
     * @param string $namespace The module's namespace, without the last `\`.
     * @param string $stem      The webhook's name without `Webhook` (`ScaffoldDelivery`).
     * @param string $path      The path it is posted to.
     * @param string $job       The job's name (`scaffold-delivery-webhook`).
     *
     * @throws WorkflowRefusal When a template is missing or a placeholder unfilled.
     *
     * @return list<MadeFile> The Api, the payload and the job.
     */
    public function files(string $folder, string $namespace, string $stem, string $path, string $job): array
    {
        $values = [
            'job' => $job,
            'namespace' => $namespace,
            'path' => $path,
            'payload' => $stem . 'Payload',
            'variable' => lcfirst($stem) . 'Payload',
            'webhook' => $stem . 'Webhook',
        ];

        return [
            $this->file($folder . 'Controller/', 'webhook', $stem . 'Webhook', $values),
            $this->file($folder . 'Service/', 'payload', $stem . 'Payload', $values),
            $this->file($folder . 'Job/', 'job', $stem . 'WebhookJob', $values),
        ];
    }

    /**
     * @param string                $folder   Where the file goes.
     * @param string                $template The template's name without `.php.tpl`.
     * @param string                $class    The class it holds.
     * @param array<string, string> $values   The other placeholders.
     *
     * @throws WorkflowRefusal When the template is missing or a placeholder unfilled.
     *
     * @return MadeFile The file.
     */
    private function file(string $folder, string $template, string $class, array $values): MadeFile
    {
        return new MadeFile(
            $folder . $class . '.php',
            $this->makeTemplates->render('webhook', $template . '.php.tpl', ['class' => $class, ...$values]),
        );
    }
}
