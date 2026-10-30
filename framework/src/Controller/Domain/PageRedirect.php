<?php

declare(strict_types=1);

namespace AIC\Controller\Domain;

use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Contracts\ResponseWriter;
use AIC\Form\Domain\SameOriginPath;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ResponseInterface;

use function in_array;
use function preg_match;

/**
 * A page answered by a redirect: 303 to a GET after a successful write, by
 * default, or another redirect status. The location is a path on this site
 * (#207): a target from the request goes through `SameOriginPath::resolve()`
 * first, and anything else is refused here.
 */
final readonly class PageRedirect implements PageResponse
{
    /**
     * The redirect statuses (RFC 9110 section 15.4).
     */
    private const array STATUSES = [301, 302, 303, 307, 308];

    /**
     * @param string $location Where to go.
     * @param int    $status   A redirect status; 303 by default.
     *
     * @throws InvalidArgumentException When the location is not a path on this site or the status is not a redirect.
     */
    public function __construct(
        private string $location,
        private int $status = 303,
    ) {
        if (preg_match(SameOriginPath::PATTERN, $location) !== 1) {
            throw new InvalidArgumentException('A page redirect goes to a path on this site, not ' . $location . '.');
        }

        if (!in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('A page redirect answers 301, 302, 303, 307 or 308; got ' . $status . '.');
        }
    }

    /**
     * @param ResponseWriter $responseWriter Writes the response as PSR-7.
     *
     * @return ResponseInterface The response.
     */
    #[Override]
    public function writeTo(ResponseWriter $responseWriter): ResponseInterface
    {
        return $responseWriter->redirect($this->status, $this->location);
    }
}
