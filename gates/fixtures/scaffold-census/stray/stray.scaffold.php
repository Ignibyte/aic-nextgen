<?php

declare(strict_types=1);

/*
 * gate:33's census fixture (#300): a template no ->file() names (rule stray).
 */

use AIC\Workflow\Domain\Scaffold\ScaffoldManifest;

return (new ScaffoldManifest('stray'))
    ->describe('A kind with a file its manifest does not name.')
    ->file('src/Service/{name}.php', 'service.php.tpl')
    ->probe(['name' => 'Probe']);
