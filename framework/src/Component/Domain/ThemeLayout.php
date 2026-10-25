<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\Layout;
use AIC\Component\Contracts\Regions;
use Override;

/** A declared document and its transparent typed page over the fragment renderer's same regions. */
final readonly class ThemeLayout implements Layout
{
    /**
     * @param ThemePage    $page          The page's data-only region carrier, rendered by the compiled typed adapter.
     * @param string       $name          The document title.
     * @param Component    $trackedAssets The public resources the head and fragment answers track.
     * @param string       $identity      The selected theme's stable identity.
     * @param list<string> $regionNames   The declared region schema.
     * @param string       $token         The visitor's CSRF token, empty before issuance.
     */
    public function __construct(
        public ThemePage $page,
        public string $name,
        public Component $trackedAssets,
        public string $identity,
        private array $regionNames,
        public string $token = '',
    ) {
    }

    /**
     * @return list<string> The selected theme's declared region names.
     */
    #[Override]
    public function names(): array
    {
        return $this->regionNames;
    }

    /**
     * @return Regions The same filled regions used for the typed page and targeted fragments.
     */
    #[Override]
    public function regions(): Regions
    {
        return $this->page->regions;
    }

    /**
     * @param Regions $regions The page's regions after block placement.
     *
     * @return self The same document with fresh typed page data over the new regions.
     */
    #[Override]
    public function withRegions(Regions $regions): self
    {
        return new self(new ThemePage($regions), $this->name, $this->trackedAssets, $this->identity, $this->regionNames, $this->token);
    }

    /**
     * @return string The explicitly selected theme's stable layout identity.
     */
    #[Override]
    public function tesseraLayout(): string
    {
        return $this->identity;
    }

    /**
     * @return string The document's title.
     */
    #[Override]
    public function title(): string
    {
        return $this->name;
    }

    /**
     * @return Component The same tracked assets written by the document and fragment response.
     */
    #[Override]
    public function assets(): Component
    {
        return $this->trackedAssets;
    }

    /**
     * @return string The visitor's CSRF token, empty before issuance.
     */
    #[Override]
    public function csrfToken(): string
    {
        return $this->token;
    }

    /**
     * @param string $csrfToken The visitor's issued CSRF token.
     *
     * @return self The document carrying the token without changing its regions or identities.
     */
    #[Override]
    public function withCsrfToken(string $csrfToken): self
    {
        return new self($this->page, $this->name, $this->trackedAssets, $this->identity, $this->regionNames, $csrfToken);
    }
}
