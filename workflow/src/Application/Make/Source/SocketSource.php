<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make\Source;

use AIC\Workflow\Application\Make\MakeTemplates;
use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * Writes make:socket's Socket (#32) from `workflow/scaffolds/socket/socket.php.tpl`.
 */
final readonly class SocketSource
{
    /**
     * @param MakeTemplates $makeTemplates Renders the template.
     */
    public function __construct(
        private MakeTemplates $makeTemplates = new MakeTemplates(),
    ) {
    }

    /**
     * @param string $namespace The module's namespace.
     * @param string $class     The Socket's class.
     * @param string $path      Its path.
     * @param string $channel   The broadcast channel it listens and publishes on.
     *
     * @throws WorkflowRefusal When the template cannot be read or rendered.
     *
     * @return string The Socket's source.
     */
    public function source(string $namespace, string $class, string $path, string $channel): string
    {
        return $this->makeTemplates->render('socket', 'socket.php.tpl', [
            'channel' => $channel,
            'class' => $class,
            'namespace' => $namespace,
            'path' => $path,
        ]);
    }
}
