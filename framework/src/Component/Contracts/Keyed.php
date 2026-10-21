<?php

declare(strict_types=1);

namespace AIC\Component\Contracts;

/**
 * A component with its own key (#70): placed in a region, it is marked
 * `<!--t:c KEY-->`, so Tessera can ask for it alone as `REGION/KEY`.
 */
interface Keyed extends Component
{
    /**
     * @return string Its key, in Tessera's key grammar.
     */
    public function key(): string;

    /**
     * @return string Its signed props token, rendered after the key (protocol 2.5, #74); empty for none.
     */
    public function props(): string;
}
