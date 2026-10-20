<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

/**
 * How a form encodes what it posts: urlencoded, or multipart when it carries
 * a file (#44).
 */
enum FormEncoding: string
{
    case Urlencoded = 'application/x-www-form-urlencoded';
    case Multipart = 'multipart/form-data';
}
