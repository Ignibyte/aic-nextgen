<?php

declare(strict_types=1);

namespace AIC\Storage\Application;

use AIC\Controller\Contracts\ResponseWriter;
use AIC\Controller\Domain\PageDocument;
use InvalidArgumentException;
use RuntimeException;

/**
 * Pages kept as private files (#265): a job renders a `PageDocument` in the app's theme, its layout
 * and regions as a visitor would get them, and keeps the whole document under a storage key, for a
 * module's Stream to send with `PrivateFiles::download()`. The document writes itself through the
 * response writer, the one reader of rendered markup (#98), and the body it answers is what is
 * kept. A kept page has no visitor, so it carries no CSRF token, and no request, so no block a
 * route places joins it. Its assets keep their canonical URLs, which resolve when the site serves it.
 *
 * @api
 */
final readonly class PageFiles
{
    /**
     * @param PrivateFiles   $privateFiles   Where the pages are kept.
     * @param ResponseWriter $responseWriter Renders a document as a response.
     */
    public function __construct(
        private PrivateFiles $privateFiles,
        private ResponseWriter $responseWriter,
    ) {
    }

    /**
     * @param string       $key          A storage key (`brochures/7.html`).
     * @param PageDocument $pageDocument The page.
     *
     * @throws InvalidArgumentException When it is not a storage key.
     * @throws RuntimeException         When the file cannot be written.
     *
     * @psalm-taint-sink client_upload $key
     */
    public function put(string $key, PageDocument $pageDocument): void
    {
        $this->privateFiles->put($key, (string) $pageDocument->writeTo($this->responseWriter)->getBody());
    }
}
