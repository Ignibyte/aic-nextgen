<?php

declare(strict_types=1);

/*
 * gate:33's census fixture (#300): a from() that names no kind (rule from).
 */

use AIC\Workflow\Domain\Scaffold\ScaffoldManifest;

return (new ScaffoldManifest('bad-from'))
    ->describe('A kind started from nothing.')
    ->from('nothing')
    ->file('src/Service/{name}.php', 'service.php.tpl')
    ->probe(['name' => 'Probe']);
