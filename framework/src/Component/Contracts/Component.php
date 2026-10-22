<?php

declare(strict_types=1);

namespace AIC\Component\Contracts;

/**
 * A server-rendered piece of a page, as data (#98): its public readonly props
 * and its template. A component never renders itself; the Renderer writes it
 * through its template, the only way markup is made (#87).
 */
interface Component
{
}
