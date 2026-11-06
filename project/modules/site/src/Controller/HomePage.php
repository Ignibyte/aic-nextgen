<?php

declare(strict_types=1);

namespace App\Site\Controller;

use AIC\Config\Domain\AppConfig;
use AIC\Controller\Application\PageDocuments;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use App\Theme\Components\Welcome\Welcome;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /: the app's home page, a welcome with the app's name from
 * config/app.php in the theme's page layout, titled Home.
 */
final readonly class HomePage implements Page
{
    /**
     * @param PageDocuments $pageDocuments Puts components in the page layout.
     * @param AppConfig     $appConfig     The app's settings.
     */
    public function __construct(
        private PageDocuments $pageDocuments,
        private AppConfig $appConfig,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request.
     *
     * @throws InvalidArgumentException When the page's document cannot be built.
     *
     * @return PageResponse The home page.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->pageDocuments->content(
            [new Welcome($this->appConfig->name, $serverRequest->getUri()->getPath())],
            200,
            'Home',
        );
    }
}
