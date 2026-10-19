<?php

declare(strict_types=1);

namespace App\Http;

use AIC\Config\Domain\AppConfig;
use AIC\Http\Application\ResponseBuilder;
use Psr\Http\Message\ResponseInterface;

use function htmlspecialchars;

/**
 * Wraps a page's body in the app's HTML document and makes the 200 response.
 */
final readonly class HtmlResponder
{
    /**
     * @param ResponseBuilder $responseBuilder Makes the response.
     * @param AppConfig       $appConfig       The app's settings; its name titles every page.
     */
    public function __construct(
        private ResponseBuilder $responseBuilder,
        private AppConfig $appConfig,
    ) {
    }

    /**
     * @param string $body The page's body markup, already escaped.
     *
     * @return ResponseInterface The 200 response with the whole document.
     */
    public function respond(string $body): ResponseInterface
    {
        $name = htmlspecialchars($this->appConfig->name);

        return $this->responseBuilder->create(
            200,
            'text/html; charset=utf-8',
            '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>' . $name . '</title></head>'
            . '<body><h1>' . $name . '</h1>' . $body . '</body></html>',
        );
    }
}
