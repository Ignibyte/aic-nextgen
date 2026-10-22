<?php

declare(strict_types=1);

namespace AIC\Form\Application;

use AIC\Form\Domain\UploadKind;
use AIC\Form\Exception\RefusedUpload;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

use function array_map;
use function implode;

/**
 * Checks an upload before an app keeps it (#44): it uploaded, it is not empty
 * or over the limit, and its first bytes are one of the allowed kinds. The
 * client's file name and media type play no part.
 */
final readonly class UploadCheck
{
    /**
     * The bytes read to tell a file's kind.
     */
    private const int HEAD = 4_096;

    /**
     * @param int              $maxBytes The largest file accepted.
     * @param list<UploadKind> $kinds    The kinds accepted.
     */
    public function __construct(
        private int $maxBytes,
        private array $kinds,
    ) {
    }

    /**
     * @param UploadedFileInterface $uploadedFile An upload.
     *
     * @throws RuntimeException When the underlying operation fails.
     *
     * @return string Its kind, for a person (`PNG image`).
     */
    public function check(UploadedFileInterface $uploadedFile): string
    {
        $refusal = $this->refusal($uploadedFile);

        if ($refusal !== '') {
            throw new RefusedUpload($refusal);
        }

        $head = $uploadedFile->getStream()->read(self::HEAD);

        foreach ($this->kinds as $kind) {
            if ($kind->matches($head)) {
                return $kind->label();
            }
        }

        throw new RefusedUpload('The file must be one of: ' . implode(', ', $this->labels()) . '.');
    }

    /**
     * @return list<string> The accepted kinds, for a person.
     */
    private function labels(): array
    {
        return array_map(static fn (UploadKind $uploadKind): string => $uploadKind->label(), $this->kinds);
    }

    /**
     * @param UploadedFileInterface $uploadedFile An upload.
     *
     * @return string Why it cannot be accepted whatever its content: it failed, is empty or is too large; empty when none.
     */
    private function refusal(UploadedFileInterface $uploadedFile): string
    {
        $size = $uploadedFile->getSize() ?? 0;

        return match (true) {
            $uploadedFile->getError() !== 0 => self::failure($uploadedFile->getError()),
            $size === 0 => 'The file is empty.',
            $size > $this->maxBytes => 'The file is larger than ' . $this->maxBytes . ' bytes.',
            default => '',
        };
    }

    /**
     * @param int $error An `UPLOAD_ERR_*` code other than 0.
     *
     * @return string Why the upload failed, for a person.
     */
    private static function failure(int $error): string
    {
        return match ($error) {
            1, 2 => 'The file is larger than the server accepts.',
            3 => 'The file arrived only in part.',
            4 => 'Choose a file to upload.',
            default => 'The upload failed.',
        };
    }
}
