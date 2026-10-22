<?php

declare(strict_types=1);

namespace App\Session\Service;

use AIC\Form\Application\UploadCheck;
use AIC\Form\Domain\UploadKind;

/**
 * The session demo's upload rules (#93), a factory the manifest lists: the
 * upload form accepts one file of at most 1 MiB, by its content, as a PNG,
 * JPEG, GIF, PDF or plain text.
 */
final readonly class UploadRules
{
    /**
     * The largest file accepted, in bytes.
     */
    private const int UPLOAD_LIMIT = 1_048_576;

    /**
     * The kinds accepted, by content.
     */
    private const array UPLOAD_KINDS = [UploadKind::Png, UploadKind::Jpeg, UploadKind::Gif, UploadKind::Pdf, UploadKind::Text];

    /**
     * @return UploadCheck The check every upload passes.
     */
    public function uploadCheck(): UploadCheck
    {
        return new UploadCheck(self::UPLOAD_LIMIT, self::UPLOAD_KINDS);
    }
}
