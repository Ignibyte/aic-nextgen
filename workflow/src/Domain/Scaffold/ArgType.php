<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Scaffold;

/**
 * What one scaffold argument may hold (#298). There is no path type: every
 * path a scaffold writes is derived from the module and the manifest.
 */
enum ArgType: string
{
    case Pascal = 'pascal';
    case Kebab = 'kebab';
    case Cron = 'cron';
    case Choice = 'choice';
    case Int = 'int';
}
