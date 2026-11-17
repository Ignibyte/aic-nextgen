<?php

declare(strict_types=1);

/*
 * gate:33's census fixture (#300): a placeholder no argument fills (rule placeholder).
 */

use AIC\Workflow\Domain\Scaffold\ScaffoldManifest;

return (new ScaffoldManifest('bad-placeholder'))
    ->describe('A kind whose template names an unknown placeholder.')
    ->file('src/Service/{name}.php', 'service.php.tpl')
    ->probe(['name' => 'Probe']);
