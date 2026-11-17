<?php

declare(strict_types=1);

/*
 * gate:33's census fixture (#300): no description, so it does not load (rule load).
 */

use AIC\Workflow\Domain\Scaffold\ScaffoldManifest;

return (new ScaffoldManifest('no-describe'))
    ->file('src/Service/{name}.php', 'service.php.tpl')
    ->probe(['name' => 'Probe']);
