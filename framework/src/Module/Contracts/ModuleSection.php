<?php

declare(strict_types=1);

namespace AIC\Module\Contracts;

/**
 * The sections of a manifest whose declarations are a bare name (#93): a
 * module's name, a class or a path, held as a `ModuleEntry`.
 *
 * Manifests build it; nothing in the framework does, so it is API for them.
 *
 * @api
 */
enum ModuleSection
{
    /**
     * A module this one uses; it is wired first.
     */
    case Requires;

    /**
     * A block, with no required constructor parameter.
     */
    case Blocks;

    /**
     * A migration under `src/Migration/`, after the ones before it.
     */
    case Migrations;

    /**
     * A class other modules may use: a component, a service, a form.
     */
    case Exports;

    /**
     * A path prefix only a signed-in visitor may open (`/account`).
     */
    case ProtectedPrefixes;

    /**
     * A readonly class whose public methods return services built from values;
     * a constructor asking for a method's return type is given its product.
     */
    case Factories;
}
