<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make\Source;

use AIC\Workflow\Application\Make\MakeTemplates;
use AIC\Workflow\Exception\WorkflowRefusal;

use function in_array;
use function strtolower;

/**
 * A policy (`make:policy`, #304): one `create` action, which needs no
 * record, allowing an actor who holds the permission the make declares
 * beside it. The agent adds the record's actions.
 */
final readonly class PolicySource
{
    /**
     * @param MakeTemplates $makeTemplates The built-in templates (#301).
     */
    public function __construct(
        private MakeTemplates $makeTemplates = new MakeTemplates(),
    ) {
    }

    /**
     * @param string $namespace  The module's namespace.
     * @param string $entity     The record type, in the module's `src/Entity/`.
     * @param string $permission What its `create` action asks.
     *
     * @throws WorkflowRefusal When its template is missing.
     *
     * @return string The file's bytes.
     */
    public function source(string $namespace, string $entity, string $permission): string
    {
        return $this->makeTemplates->render('policy', 'policy.php.tpl', [
            'article' => in_array(strtolower($entity[0] ?? ''), ['a', 'e', 'i', 'o', 'u'], true) ? 'an' : 'a',
            'class' => $entity . 'Policy',
            'entity' => $entity,
            'namespace' => $namespace,
            'permission' => $permission,
        ]);
    }
}
