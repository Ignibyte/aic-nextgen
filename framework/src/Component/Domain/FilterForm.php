<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

use AIC\Component\Contracts\Component;

/**
 * A one-field GET form that filters a list (#230): it carries no token, as it
 * changes nothing, and Tessera answers it in place when `target` names the
 * list.
 */
final readonly class FilterForm implements Component
{
    /**
     * @param string $action Where it sends the query.
     * @param string $name   The field's name, the query parameter.
     * @param string $value  The field's current value.
     * @param string $label  The field's label.
     * @param string $target The Tessera target it refreshes (`@name`); empty for a whole visit.
     */
    public function __construct(
        public string $action,
        public string $name,
        public string $value,
        public string $label,
        public string $target = '',
    ) {
    }
}
