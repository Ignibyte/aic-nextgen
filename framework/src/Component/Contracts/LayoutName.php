<?php

declare(strict_types=1);

namespace AIC\Component\Contracts;

/**
 * A theme layout's name (#154). The build generates the one enum that implements it, `App\Generated\ThemeLayoutName`,
 * with a case per layout the theme ships, so a controller can name only a layout that exists.
 */
interface LayoutName
{
}
