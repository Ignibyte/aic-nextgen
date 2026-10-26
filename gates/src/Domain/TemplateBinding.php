<?php

declare(strict_types=1);

namespace Gates\Domain;

use AIC\Component\Contracts\Component;

/** A validated authored template's input and its escaped output (#155). */
final readonly class TemplateBinding
{
    /**
     * @param class-string<Component> $component The actual typed input class.
     * @param string                  $source    Its project-relative authored source.
     * @param string                  $generated Its project-relative escaped template, the `.phtml` its lookup
     *                                           requires.
     * @param TemplateRole            $role      Whether children carry component markers or remain transparent.
     */
    public function __construct(
        public string $component,
        public string $source,
        public string $generated,
        public TemplateRole $role,
    ) {
    }

    /**
     * @return bool Whether the declared role writes children inline without changing their target paths.
     */
    public function transparent(): bool
    {
        return $this->role !== TemplateRole::Component;
    }
}
