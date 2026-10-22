<?php

declare(strict_types=1);

namespace AIC\Sapi\Infrastructure;

use AIC\Sapi\Contracts\SapiUpload;

use function is_int;
use function is_string;

/**
 * Narrows native SAPI names and the single-file upload shape AIC accepts.
 * Unsupported upload members keep the same empty or failed-upload defaults.
 */
final readonly class SapiInput
{
    /**
     * @param string|int $key A native SAPI array key.
     *
     * @return string|null Its name, or null for an unsupported numeric key.
     */
    public function name(string|int $key): ?string
    {
        return is_string($key)
            ? $key
            : null;
    }

    /**
     * @param string|int               $field The form field's key.
     * @param array<array-key, scalar> $file  The native scalar upload members; unsupported arrays are omitted.
     *
     * @return SapiUpload|null A single upload; null when its field has no string name.
     */
    public function upload(string|int $field, array $file): ?SapiUpload
    {
        $name = $this->name($field);

        if ($name === null) {
            return null;
        }

        return $this->file($name, $file);
    }

    /**
     * @param string                   $name The upload field's name.
     * @param array<array-key, scalar> $file Its native scalar members.
     *
     * @return SapiUpload The supported values and defaults for unsupported members.
     */
    private function file(string $name, array $file): SapiUpload
    {
        $clientName = $file['name'] ?? '';
        $clientType = $file['type'] ?? '';
        $path = $file['tmp_name'] ?? '';
        $error = $file['error'] ?? 4;
        $size = $file['size'] ?? 0;

        return new SapiUpload(
            $name,
            is_string($clientName) ? $clientName : '',
            is_string($clientType) ? $clientType : '',
            is_string($path) ? $path : '',
            is_int($error) ? $error : 4,
            is_int($size) ? $size : 0,
        );
    }
}
