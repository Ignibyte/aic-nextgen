<?php

declare(strict_types=1);

namespace Gates\Domain;

use function preg_quote;

/**
 * One request of the native site script (gate:23): sent to the interpreted
 * site and to the compiled one, whose answers must match.
 */
final readonly class NativeSiteStep
{
    use RegexCaptures;

    /**
     * @param string       $name     What the step checks, for the report.
     * @param string       $method   The HTTP method.
     * @param string       $path     The path; `{note}` stands for the Location the capturing step kept,
     *                               `{location}` for the last Location with its query (#212).
     * @param string       $form     A form-encoded body; empty for none.
     * @param string       $header   One extra request header line (`Range: bytes=0-4`); empty for none.
     * @param int          $status   The status both sites must answer.
     * @param bool         $captures Whether its Location is kept as `{note}` for the later steps.
     * @param string       $capture  A path prefix: the first link in its answer under it is kept as `{note}` (#200), for
     *                               a record whose page no Location names; empty for none.
     * @param int          $events   For an event stream that stays open, how many events to read before hanging up (#202);
     *                               0 reads it to its end.
     * @param bool         $work     Whether the app's queue worker runs before it is sent (#209), for a page that exists
     *                               only after a job runs.
     * @param list<string> $console  An app command and its arguments, run before it is sent and before the worker
     *                               (#210), so each pass starts a record afresh; empty for none.
     */
    public function __construct(
        public string $name,
        public string $method,
        public string $path,
        public string $form,
        public string $header,
        public int $status,
        public bool $captures,
        public string $capture = '',
        public int $events = 0,
        public bool $work = false,
        public array $console = [],
    ) {
    }

    /**
     * @param string $location Its answer's Location, as a path.
     * @param string $body     Its answer's body.
     *
     * @return string The path it keeps as `{note}`: the first link under its prefix, or its Location; empty when it
     *                keeps none.
     */
    public function captured(string $location, string $body): string
    {
        if ($this->capture !== '') {
            return self::regexMatch('#href="(' . preg_quote($this->capture, '#') . '[^"?\\#]*)#', $body)[1] ?? '';
        }

        return $this->captures
            ? $location
            : '';
    }
}
