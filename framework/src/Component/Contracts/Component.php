<?php

declare(strict_types=1);

namespace AIC\Component\Contracts;

/**
 * A server-rendered piece of a page. Its props are its constructor's
 * parameters, and a component that holds another renders it itself: there is
 * no renderer looking classes up.
 */
interface Component
{
    /**
     * @return Html The component's markup.
     */
    public function render(): Html;
}
