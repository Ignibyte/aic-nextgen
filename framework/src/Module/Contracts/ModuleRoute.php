<?php

declare(strict_types=1);

namespace AIC\Module\Contracts;

/**
 * One route a module declares: its method, its path pattern and the controller
 * that answers it. The controller's kind (Page, Api or Stream) is read from its
 * class at build time, never declared a second time (#26).
 */
final readonly class ModuleRoute implements ModuleDeclaration
{
    /**
     * @param RouteMethod  $method  GET or POST.
     * @param string       $path    The path pattern, as `RouteCollector` takes it (`/faq`, `/notes/{id:int}`).
     * @param class-string $handler The controller class, in the module's own namespace.
     */
    public function __construct(
        public RouteMethod $method,
        public string $path,
        public string $handler,
    ) {
    }
}
