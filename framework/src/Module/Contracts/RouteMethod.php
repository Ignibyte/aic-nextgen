<?php

declare(strict_types=1);

namespace AIC\Module\Contracts;

/**
 * The HTTP method a module's route answers: the two the route collector offers.
 * Manifests name the cases, so both are API whether or not a module uses them.
 *
 * @api
 */
enum RouteMethod
{
    /** A GET route (`RouteCollector::get()`). */
    case Get;

    /** A POST route (`RouteCollector::post()`). */
    case Post;
}
