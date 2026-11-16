<?php

declare(strict_types=1);

namespace {namespace}\Service;

use JsonSerializable;
use Override;

/**
 * The body of {api}, made by bin/aic make:api (#122).
 */
final readonly class {class} implements JsonSerializable
{
    /**
     * @param string $module The module that answers.
     */
    public function __construct(
        private string $module,
    ) {
    }

    /**
     * @return array<string, string> The JSON object.
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return ['module' => $this->module];
    }
}
