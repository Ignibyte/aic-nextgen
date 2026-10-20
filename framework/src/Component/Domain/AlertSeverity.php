<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

/**
 * How serious an alert is; rendered as its `data-severity`. Further cases join
 * with their first caller.
 */
enum AlertSeverity: string
{
    /** Something the visitor must fix: a refused form. */
    case Error = 'error';
}
