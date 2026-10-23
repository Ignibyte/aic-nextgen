<?php

declare(strict_types=1);

namespace Gates\Infrastructure;

use RuntimeException;
use Throwable;

use function is_file;
use function is_int;
use function is_readable;

/**
 * Reads a project's typed configuration at the build boundary, preserving load failures.
 */
final readonly class ProjectConfig
{
    /**
     * @template T of object
     *
     * @param string          $file The configuration's absolute path.
     * @param class-string<T> $type The required configuration type.
     *
     * @throws RuntimeException When the file is unavailable, loading fails or its result has the wrong type.
     *
     * @return T The checked configuration object.
     */
    public function read(string $file, string $type): object
    {
        if (!is_file($file) || !is_readable($file)) {
            throw new RuntimeException('Cannot read configuration: ' . $file);
        }

        try {
            $loaded = [require $file];
        } catch (Throwable $throwable) {
            throw $this->failure($file, $throwable);
        }

        if (!$loaded[0] instanceof $type) {
            throw new RuntimeException('Configuration ' . $file . ' must return ' . $type . '.');
        }

        return $loaded[0];
    }

    /**
     * @param string    $file      The failed configuration path.
     * @param Throwable $throwable The original load failure, whose code may be a string.
     *
     * @return RuntimeException The located build failure with its original cause.
     */
    private function failure(string $file, Throwable $throwable): RuntimeException
    {
        $code = $throwable->getCode();

        return new RuntimeException(
            'Cannot load configuration: ' . $file . ': ' . $throwable->getMessage(),
            is_int($code) ? $code : 0,
            previous: $throwable,
        );
    }
}
