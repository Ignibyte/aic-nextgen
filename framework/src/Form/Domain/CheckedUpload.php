<?php

declare(strict_types=1);

namespace AIC\Form\Domain;

/**
 * What `UploadCheck::kind()` found an upload to be (#213): its kind for a
 * person and the extension it is stored under. A value rather than the
 * `UploadKind` itself, because natively an enum case taken from a loop over
 * a list of cases loses its type (#201), so the check reads both inside its
 * loop.
 */
final readonly class CheckedUpload
{
    /**
     * @param string $label     Its kind, for a person (`PNG image`).
     * @param string $extension The extension it is stored under (`png`), which decides how `/files/` sends it.
     */
    public function __construct(
        public string $label,
        public string $extension,
    ) {
    }
}
