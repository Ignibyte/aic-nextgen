<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Guard;

/**
 * How hard a guard lever holds (#102, Droost's `enforcement` and
 * `require_run`): hard refuses, soft nudges once per run, phase and rule,
 * off stands the wall down.
 */
enum Enforcement: string
{
    case Hard = 'hard';
    case Soft = 'soft';
    case Off = 'off';
}
