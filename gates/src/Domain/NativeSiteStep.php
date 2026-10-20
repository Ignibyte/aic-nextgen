<?php

declare(strict_types=1);

namespace Gates\Domain;

/**
 * One request of the native site script (gate:23): sent to the interpreted
 * site and to the compiled one, whose answers must match.
 */
final readonly class NativeSiteStep
{
    /**
     * @param string $name     What the step checks, for the report.
     * @param string $method   The HTTP method.
     * @param string $path     The path; `{note}` stands for the Location the capturing step kept.
     * @param string $form     A form-encoded body; empty for none.
     * @param string $header   One extra request header line (`Range: bytes=0-4`); empty for none.
     * @param int    $status   The status both sites must answer.
     * @param bool   $database Whether the step needs Postgres.
     * @param bool   $captures Whether its Location is kept as `{note}` for the later steps.
     */
    public function __construct(
        public string $name,
        public string $method,
        public string $path,
        public string $form,
        public string $header,
        public int $status,
        public bool $database,
        public bool $captures,
    ) {
    }
}
