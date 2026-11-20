<?php

declare(strict_types=1);

namespace App\Ledger\Controller;

use AIC\Controller\Application\PageDocuments;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use App\Theme\Components\Meter\Meter;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function array_key_exists;
use function ctype_digit;
use function is_string;
use function max;
use function min;

/**
 * GET /ledger/meter?level=N (#315): the Meter, whose script Tessera mounts
 * and draws N bars with (0 to 8, 3 by default; 0 hides it), with links that
 * change the level by one, keep it, or hide it.
 */
final readonly class LedgerMeterPage implements Page
{
    /**
     * @param PageDocuments $pageDocuments Makes the page.
     */
    public function __construct(
        private PageDocuments $pageDocuments,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, perhaps with `level`.
     *
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return PageResponse The page.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $query = $serverRequest->getQueryParams();
        $asked = array_key_exists('level', $query) && is_string($query['level']) && ctype_digit($query['level'])
            ? (int) $query['level']
            : 3;
        $level = min(8, $asked);
        $meter = new Meter($level, '{"level":' . $level . '}', max(0, $level - 1), min(8, $level + 1));

        return $this->pageDocuments->content([$meter], 200, 'Ledger meter');
    }
}
