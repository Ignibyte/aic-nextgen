<?php

declare(strict_types=1);

namespace App\Generated\Templates;

use AIC\Component\Domain\RendersMarkup;
use App\Theme\Components\StockListing\StockListing;

/**
 * StockListing's markup, compiled from
 * themes/app/components/StockListing/stock-listing.tpl.php by composer run build (#97, #98):
 * edit the template and run composer run build, never this file.
 */
final readonly class StockListingTemplate
{
    use RendersMarkup;

    /**
     * @param StockListing $stockListing The component.
     *
     * @return string Its markup.
     */
    public function render(StockListing $stockListing): string
    {
        return $this->part2($stockListing);
    }

    /**
     * @param string $line A loop's variable.
     *
     * @return string Markup from themes/app/components/StockListing/stock-listing.tpl.php.
     */
    private function part0(string $line): string
    {
        return '<li>'
            . self::text($line)
            . '</li>';
    }

    /**
     * @param StockListing $stockListing The component.
     *
     * @return string Markup from themes/app/components/StockListing/stock-listing.tpl.php.
     */
    private function part1(StockListing $stockListing): string
    {
        $markup = '';

        foreach ($stockListing->lines as $line) {
            $markup .= $this->part0($line);
        }

        return $markup;
    }

    /**
     * @param StockListing $stockListing The component.
     *
     * @return string Markup from themes/app/components/StockListing/stock-listing.tpl.php.
     */
    private function part2(StockListing $stockListing): string
    {
        return '<p id="stock-limit">The last '
            . self::text((string) ($stockListing->limit))
            . '.</p><ul id="stock">'
            . '<!--t:b-->'
            . $this->part1($stockListing)
            . '<!--/t:b-->'
            . '</ul>';
    }
}
