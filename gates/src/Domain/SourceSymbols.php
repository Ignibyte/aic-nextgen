<?php

declare(strict_types=1);

namespace Gates\Domain;

/**
 * What one PHP source file declares and refers to, as the native gate (gate:21) reads it.
 */
final readonly class SourceSymbols
{
    /**
     * @param string|null  $declared   FQCN of the first class, interface, trait or enum the file declares.
     * @param list<string> $candidates Every name that may refer to a class, both as written and
     *                                 qualified by the file's namespace; resolving them decides.
     */
    public function __construct(
        public ?string $declared,
        public array $candidates,
    ) {
    }
}
