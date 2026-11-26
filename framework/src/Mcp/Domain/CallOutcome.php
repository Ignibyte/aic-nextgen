<?php

declare(strict_types=1);

namespace AIC\Mcp\Domain;

/**
 * How a capability's call ended (#332), as its audit row records it.
 */
enum CallOutcome: string
{
    /** The tool answered its output. */
    case Done = 'done';

    /** The tool refused the call, with a reason for the caller. */
    case Refused = 'refused';

    /** The arguments did not fit the tool's input. */
    case Invalid = 'invalid';

    /** The tool failed; its writes were rolled back. */
    case Failed = 'failed';
}
