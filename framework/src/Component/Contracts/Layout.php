<?php

declare(strict_types=1);

namespace AIC\Component\Contracts;

/**
 * A page's document: a component that names its regions and places each one
 * (#37). The page fills the regions; the layout decides where they go.
 */
interface Layout extends Component
{
    /**
     * @return list<string> The regions it places, by name.
     */
    public function names(): array;

    /**
     * @return Regions The regions the page filled.
     */
    public function regions(): Regions;
}
