<?php

declare(strict_types=1);

namespace Gates\Domain;

use function sprintf;

/**
 * One construct gate:25 refuses (#96): where it is, its category, and what
 * about it is refused.
 */
final readonly class TemplateViolation
{
    /**
     * @param string          $path            The template, relative to the root it was found under.
     * @param int             $line            The construct's first line.
     * @param TemplateRefusal $templateRefusal Its category.
     * @param string          $detail          What is refused, and the fix when there is one.
     */
    public function __construct(
        public string $path,
        public int $line,
        public TemplateRefusal $templateRefusal,
        public string $detail,
    ) {
    }

    /**
     * @return string The line gate:25 prints: `path:line: category: detail`.
     */
    public function message(): string
    {
        return sprintf('%s:%d: %s: %s', $this->path, $this->line, $this->templateRefusal->value, $this->detail);
    }
}
