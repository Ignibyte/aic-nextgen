<?php

declare(strict_types=1);

namespace AIC\Controller\Domain;

use AIC\Component\Contracts\Layout;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Contracts\ResponseWriter;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ResponseInterface;

use function in_array;

/**
 * A page answered as a whole HTML document, its layout rendered with the
 * regions the page filled (#37): 200; 404 when what it shows does not exist;
 * or 422 when a form posted to it is rendered again in place with its errors.
 */
final readonly class PageDocument implements PageResponse
{
    /**
     * The statuses a document may carry.
     */
    private const array STATUSES = [200, 404, 422];

    /**
     * @param Layout $layout The document, with its regions filled.
     * @param int    $status 200, 404 for a missing thing, or 422 for a form shown again with its errors.
     *
     * @throws InvalidArgumentException When the status is none of these, or a filled region is one the layout does not place.
     */
    public function __construct(
        private Layout $layout,
        private int $status = 200,
    ) {
        if (!in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('A page document answers 200, 404 or 422; got ' . $status . '.');
        }

        foreach ($layout->regions()->names() as $name) {
            if (!in_array($name, $layout->names(), true)) {
                throw new InvalidArgumentException('The layout places no region "' . $name . '".');
            }
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
        return $responseWriter->document($this->status, $this->layout->render()->markup());
    }
}
