<?php

declare(strict_types=1);

namespace AIC\Profiler\Domain;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\Layout;
use AIC\Component\Contracts\Region;
use AIC\Component\Contracts\Regions;
use AIC\Component\Domain\TrackedAssets;
use InvalidArgumentException;
use Override;

/**
 * The profiler pages' document (#230), the framework's own, so the pages look
 * the same whatever the app's theme: one `content` region, the profiler's
 * stylesheet and the pinned Tessera script. Its template prints no CSRF
 * token, as the pages only read. Its template is a framework atom.
 */
final readonly class ProfilerLayout implements Layout
{
    /** Its Tessera layout identity; a fragment answer needs the page that asked to carry the same. */
    public const string IDENTITY = 'profiler';

    /** The one region it places. */
    public const string CONTENT = 'content';

    /** The regions, its content alone. */
    private Regions $regions;

    /**
     * @param string        $name          The page's title.
     * @param Region        $content       What the page holds, its `content` region.
     * @param TrackedAssets $trackedAssets The stylesheet and the Tessera script.
     * @param string        $identity      Its Tessera layout identity, printed in the `tessera-layout` meta.
     * @param string        $token         A token the framework issued; kept, never printed.
     *
     * @throws InvalidArgumentException When the regions cannot be made from the content region.
     */
    public function __construct(
        public string $name,
        public Region $content,
        public TrackedAssets $trackedAssets,
        public string $identity = self::IDENTITY,
        private string $token = '',
    ) {
        $this->regions = new Regions([$content]);
    }

    /**
     * @return list<string> The regions it places.
     */
    #[Override]
    public function names(): array
    {
        return [self::CONTENT];
    }

    /**
     * @return string The page's title.
     */
    #[Override]
    public function title(): string
    {
        return $this->name;
    }

    /**
     * @return string Its Tessera layout identity.
     */
    #[Override]
    public function tesseraLayout(): string
    {
        return $this->identity;
    }

    /**
     * @return Component The stylesheet and the Tessera script.
     */
    #[Override]
    public function assets(): Component
    {
        return $this->trackedAssets;
    }

    /**
     * @return Regions What the page filled.
     */
    #[Override]
    public function regions(): Regions
    {
        return $this->regions;
    }

    /**
     * @return string The token it was given; the pages issue none, as they only read.
     */
    #[Override]
    public function csrfToken(): string
    {
        return $this->token;
    }

    /**
     * @param Regions $regions The regions, blocks placed.
     *
     * @throws InvalidArgumentException When the regions cannot be made from the content region.
     *
     * @return self It with their content region.
     */
    #[Override]
    public function withRegions(Regions $regions): self
    {
        return $this->with($regions->region(self::CONTENT), $this->token);
    }

    /**
     * @param string $csrfToken A token.
     *
     * @throws InvalidArgumentException When the regions cannot be made from the content region.
     *
     * @return self It with the token.
     */
    #[Override]
    public function withCsrfToken(string $csrfToken): self
    {
        return $this->with($this->content, $csrfToken);
    }

    /**
     * @param Region $region The content region.
     * @param string $token  The token.
     *
     * @throws InvalidArgumentException When the regions cannot be made from the content region.
     *
     * @return self A copy with both.
     */
    private function with(Region $region, string $token): self
    {
        return new self($this->name, $region, $this->trackedAssets, $this->identity, $token);
    }
}
