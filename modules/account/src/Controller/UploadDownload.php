<?php

declare(strict_types=1);

namespace App\Account\Controller;

use AIC\Controller\Contracts\Stream;
use AIC\Controller\Contracts\StreamResponse;
use App\Account\Service\KeptUploads;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * `GET /uploads/latest` (#213): the last accepted upload's private copy, from
 * the app's private files, as a download; one byte range honoured.
 */
final readonly class UploadDownload implements Stream
{
    /**
     * @param KeptUploads $keptUploads The kept copies.
     */
    public function __construct(
        private KeptUploads $keptUploads,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request.
     *
     * @throws InvalidArgumentException When the key is malformed.
     *
     * @return StreamResponse The private copy; 404 before the first upload.
     */
    #[Override]
    public function stream(ServerRequestInterface $serverRequest): StreamResponse
    {
        return $this->keptUploads->download($serverRequest);
    }
}
