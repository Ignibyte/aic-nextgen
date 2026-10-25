<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Make;

/**
 * The call a make adds to a module's manifest (#122), such as
 * `->route(RouteMethod::Get, '/scaffold', ScaffoldPage::class)`, with the
 * classes its `use` lines name.
 */
final readonly class ManifestCall
{
    /**
     * @param string       $call The call, from its `->`.
     * @param list<string> $uses The classes it names, fully qualified.
     */
    public function __construct(
        public string $call,
        public array $uses,
    ) {
    }
}
