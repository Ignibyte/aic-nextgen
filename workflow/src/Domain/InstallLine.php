<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

use function str_pad;

/**
 * One line of `workflow:init`'s report (#105): a path, what was done with it
 * and, when it was not plain, why.
 */
final readonly class InstallLine
{
    /**
     * @param InstallAction $installAction What was done.
     * @param string        $path          The path, relative to the project; a region as `AGENTS.md#aic-workflow`.
     * @param string        $why           Why, when it needs saying; empty otherwise.
     */
    public function __construct(
        public InstallAction $installAction,
        public string $path,
        public string $why = '',
    ) {
    }

    /**
     * @return string The line as printed: the action, padded, the path, then the reason.
     */
    public function text(): string
    {
        $text = str_pad($this->installAction->value, 8) . $this->path;

        return $this->why === ''
            ? $text
            : $text . ' — ' . $this->why;
    }
}
