<?php

declare(strict_types=1);

namespace AIC\Routing\Domain;

/**
 * The types a route parameter can be declared with, as `{name:type}`; `any` when
 * none is given.
 */
enum ParameterType: string
{
    case Int = 'int';
    case Slug = 'slug';
    case Uuid = 'uuid';
    case Any = 'any';

    /**
     * @return string The regex one path segment of this type matches.
     */
    public function regex(): string
    {
        return match ($this) {
            self::Int => '\\d+',
            self::Slug => '[a-z0-9](?:[a-z0-9-]*[a-z0-9])?',
            self::Uuid => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}',
            self::Any => '[^/]+',
        };
    }
}
