<?php

declare(strict_types=1);

namespace AIC\Form\Domain;

use function preg_match;

/**
 * Keeps a redirect on this site (#43): a target supplied by a request is
 * used only when it is a path here, with no scheme or host, not `//`
 * (protocol-relative), no backslash and no control character; anything
 * else becomes the fallback.
 */
final readonly class SameOriginPath
{
    /**
     * A path on this site: a leading `/` not followed by `/` or `\`, then no
     * backslash and no control character. `PageRedirect` holds every location to it.
     */
    public const string PATTERN = '#^/(?![/\\\\])[^\\\\\\x00-\\x1f\\x7f]*$#';

    /**
     * @param string $fallback Where to go instead: a path on this site.
     */
    public function __construct(
        private string $fallback,
    ) {
    }

    /**
     * @param string $candidate A target from the request.
     *
     * @return string It, when it is a path on this site; the fallback otherwise.
     */
    public function resolve(string $candidate): string
    {
        return preg_match(self::PATTERN, $candidate) === 1
            ? $candidate
            : $this->fallback;
    }
}
