<?php

declare(strict_types=1);

namespace App\Scaffold\Enum;

/**
 * The states of `scaffold_tasks.stage`, made by bin/aic make:state (#308): the
 * values its Choice field holds, the first where every new row starts.
 */
enum ScaffoldTaskStage: string
{
    case Open = 'open';

    case Done = 'done';

    case Archived = 'archived';
}
