<?php

declare(strict_types=1);

namespace AIC\Frankenphp\Contracts;

use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Turns one SAPI request into a PSR-7 server request. The kernel depends on this
 * contract, so the SAPI adapter stays in Infrastructure and the kernel above it.
 */
interface RequestExtractor
{
    /**
     * @param SapiRequest $sapiRequest The request as the SAPI gave it.
     *
     * @throws InvalidArgumentException When the request cannot be made; a
     *                                  {@see \AIC\Http\Domain\MalformedInputException}
     *                                  when its input does not fit the field shapes.
     *
     * @return ServerRequestInterface The PSR-7 request.
     */
    public function extract(SapiRequest $sapiRequest): ServerRequestInterface;
}
