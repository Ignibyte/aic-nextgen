<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Import;

/**
 * What a token of a schema dump is (#318).
 */
enum DumpTokenKind
{
    case Word;
    case Quoted;
    case Text;
    case Number;
    case Symbol;
    case Body;
}
