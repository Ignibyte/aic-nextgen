<?php

declare(strict_types=1);

namespace AIC\Form\Domain;

use function mb_check_encoding;
use function str_contains;
use function str_starts_with;
use function substr;

/**
 * A kind of file an upload may be (#44), told by its first bytes, never by
 * the name or type the client sent. WebP is a RIFF container whose form type
 * is `WEBP` (#196).
 */
enum UploadKind
{
    case Png;
    case Jpeg;
    case Gif;
    case WebP;
    case Pdf;
    case Text;

    /**
     * @return string Its name, for a person.
     */
    public function label(): string
    {
        return match ($this) {
            self::Png => 'PNG image',
            self::Jpeg => 'JPEG image',
            self::Gif => 'GIF image',
            self::WebP => 'WebP image',
            self::Pdf => 'PDF document',
            self::Text => 'plain text',
        };
    }

    /**
     * @param string $head A file's first bytes.
     *
     * @return bool Whether they are this kind's: its signature, or for text, UTF-8 with no NUL.
     */
    public function matches(string $head): bool
    {
        return match ($this) {
            self::Png => str_starts_with($head, "\x89PNG\r\n\x1a\n"),
            self::Jpeg => str_starts_with($head, "\xFF\xD8\xFF"),
            self::Gif => str_starts_with($head, 'GIF87a') || str_starts_with($head, 'GIF89a'),
            self::WebP => str_starts_with($head, 'RIFF') && substr($head, 8, 4) === 'WEBP',
            self::Pdf => str_starts_with($head, '%PDF-'),
            self::Text => $head !== '' && !str_contains($head, "\0") && mb_check_encoding($head, 'UTF-8'),
        };
    }
}
