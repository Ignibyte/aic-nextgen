<?php

declare(strict_types=1);

/*
 * The poller scaffold (#298), AIC's fixture for app scaffolds: a scheduled job
 * that asks one service for what changed upstream. `bin/aic make poller
 * <module> <Name> [--every=<cron>]` writes both classes and adds the job and
 * its schedule to the module's manifest.
 */

use AIC\Workflow\Domain\Scaffold\ArgType;
use AIC\Workflow\Domain\Scaffold\ScaffoldManifest;

return (new ScaffoldManifest('poller'))
    ->describe('A scheduled job that asks one service for what changed in an upstream system.')
    ->notFor('An upstream that calls us: use an Api with a signature check.')
    ->notFor('A one-off import someone runs by hand: use make:command.')
    ->from('job')
    ->arg('every', ArgType::Cron, '*/5 * * * *')
    ->file('src/Job/{name}Poll.php', 'job.php.tpl')
    ->file('src/Service/{name}Poller.php', 'service.php.tpl')
    ->job('{name|kebab}-poll', 'Job\\{name}Poll')
    ->schedule('{name|kebab}-poll', '{name|kebab}-poll', '{every}')
    ->probe(['name' => 'Probe']);
