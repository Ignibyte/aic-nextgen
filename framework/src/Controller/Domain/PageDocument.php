<?php

declare(strict_types=1);

namespace AIC\Controller\Domain;

use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Contracts\ResponseWriter;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ResponseInterface;

use function in_array;

/**
 * A page answered as a whole HTML document: 200, or 422 when a form posted to
 * it is rendered again in place with its errors.
 */
final readonly class PageDocument implements PageResponse
{
    /**
     * The statuses a document may carry.
     */
    private const array STATUSES = [200, 422];

    /**
     * @param string $html   The whole document, escaped where it holds input.
     * @param int    $status 200, or 422 for a form shown again with its errors.
     *
     * @throws InvalidArgumentException When the status is neither.
     */
    public function __construct(
        private string $html,
        private int $status = 200,
    ) {
        if (!in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('A page document answers 200 or 422; got ' . $status . '.');
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
        return $responseWriter->document($this->status, $this->html);
    }
}
