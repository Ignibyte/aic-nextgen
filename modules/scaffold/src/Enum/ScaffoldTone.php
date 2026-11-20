<?php

declare(strict_types=1);

namespace App\Scaffold\Enum;

/**
 * Scaffold Tone: an enum of the module, made by bin/aic make:enum (#319), with a
 * case for each value it takes. Nothing reads it until the module does; it is
 * API for it.
 *
 * @api
 */
enum ScaffoldTone: string
{
    case Calm = 'calm';

    case Loud = 'loud';

    case OnHold = 'on_hold';
}
