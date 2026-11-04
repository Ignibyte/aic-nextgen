<?php

declare(strict_types=1);

namespace App\Site\Controller;

use AIC\Controller\Contracts\Stream;
use AIC\Controller\Contracts\StreamResponse;
use AIC\Storage\Application\PrivateFiles;
use App\Site\Job\KeptPageJob;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /kept-page: the page the kept-page job keeps (#265), sent inline, so it opens in the browser
 * with the site's assets; 404 until the job has run.
 */
final readonly class KeptPageDownload implements Stream
{
    /**
     * @param PrivateFiles $privateFiles The app's private files.
     */
    public function __construct(
        private PrivateFiles $privateFiles,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request: its `Range` and conditions.
     *
     * @throws InvalidArgumentException When the key is invalid.
     *
     * @return StreamResponse The page, validated and ranged.
     */
    #[Override]
    public function stream(ServerRequestInterface $serverRequest): StreamResponse
    {
        return $this->privateFiles->download(KeptPageJob::KEY, 'text/html; charset=utf-8', '', $serverRequest);
    }
}
