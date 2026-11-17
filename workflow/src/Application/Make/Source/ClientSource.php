<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make\Source;

use AIC\Workflow\Application\Make\MakeTemplates;
use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * A typed client of one upstream (`make:client`, #305) and the reply value it
 * answers with: one `get` call over PSR-18, its base URL and token from
 * config/app.php's upstreams. The agent adds the upstream's own calls.
 */
final readonly class ClientSource
{
    /**
     * @param MakeTemplates $makeTemplates The built-in templates (#301).
     */
    public function __construct(
        private MakeTemplates $makeTemplates = new MakeTemplates(),
    ) {
    }

    /**
     * @param string $namespace The module's namespace.
     * @param string $name      The upstream's class stem, `Billing`.
     * @param string $upstream  Its name in config/app.php, `billing`.
     *
     * @throws WorkflowRefusal When a template is missing.
     *
     * @return array{string, string} The client's bytes, then the reply's.
     */
    public function sources(string $namespace, string $name, string $upstream): array
    {
        $values = ['namespace' => $namespace, 'reply' => $name . 'Reply', 'upstream' => $upstream];

        return [
            $this->makeTemplates->render('client', 'client.php.tpl', [...$values, 'class' => $name . 'Client']),
            $this->makeTemplates->render('client', 'reply.php.tpl', [...$values, 'class' => $name . 'Reply']),
        ];
    }
}
