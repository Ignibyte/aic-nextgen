<?php

declare(strict_types=1);

namespace App\Site\Block;

use AIC\Block\Contracts\Block;
use AIC\Block\Contracts\Placement;
use AIC\Component\Contracts\Component;
use AIC\Component\Domain\KeyedComponent;
use AIC\Component\Domain\Link;
use App\Site\Component\SiteNav;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function str_starts_with;

/**
 * The demo's navigation (#58), in `nav` on every page, first: a link to each
 * main demo, the current one marked from the page's request. Tessera enhances
 * it (#70): a click updates `content` and `nav` from fragments.
 */
final readonly class SiteNavBlock implements Block
{
    /**
     * Each entry's label by its path.
     */
    private const array ENTRIES = [
        '/' => 'Home',
        '/notes' => 'Notes',
        '/pages' => 'Pages',
        '/stock' => 'Stock',
    ];

    /**
     * @return Placement `nav`, weight 0, every page.
     */
    #[Override]
    public function placement(): Placement
    {
        return new Placement('nav', 0, '');
    }

    /**
     * @param ServerRequestInterface $serverRequest The page's request, whose path picks the current entry.
     *
     * @return Component `<nav id="site-nav">`.
     */
    #[Override]
    public function component(ServerRequestInterface $serverRequest): Component
    {
        $path = $serverRequest->getUri()->getPath();
        $links = [];

        foreach (self::ENTRIES as $href => $label) {
            $links[] = new KeyedComponent($label, new Link($label, $href, self::isCurrent($href, $path), 'content nav'));
        }

        return new SiteNav($links);
    }

    /**
     * @param string $href An entry's path.
     * @param string $path The page's path.
     *
     * @return bool Whether the entry is the page or a parent of it; `/` is current only on itself.
     */
    private static function isCurrent(string $href, string $path): bool
    {
        return $path === $href || ($href !== '/' && str_starts_with($path, $href . '/'));
    }
}
