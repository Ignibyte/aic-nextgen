<?php

declare(strict_types=1);

namespace Gates\Domain;

/**
 * What one node of a lowered template is (#36, #97): the compiler writes each
 * kind its own way.
 */
enum TemplateNodeKind
{
    /** Markup written as it is. */
    case Text;

    /** `<?= string ?>`, or a number cast to one: escaped. */
    case Escaped;

    /** `<?= Html ?>` or a region: written as it is. */
    case Raw;

    /** `<?= Component ?>`: rendered between Tessera's component markers. */
    case Component;

    /** `if (cond): ... else: ... endif`: between Tessera's block markers. */
    case Condition;

    /** An `elseif` (or a `switch` case after the first): a condition inside its chain's markers. */
    case ElseIf;

    /** `foreach (list as $item): ... endforeach`: a real loop, between block markers. */
    case Loop;

    /** `for (init; cond; step): ... endfor`: a real loop, between block markers. */
    case Counter;

    /** `break` inside a loop. */
    case Break;

    /** `continue` inside a loop. */
    case Continue;

    /** A class the generated code names: an import for the generated class. */
    case Import;
}
