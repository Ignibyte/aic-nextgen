<?php

declare(strict_types=1);

namespace AIC\Form\Exception;

use RuntimeException;

/**
 * An upload was refused; the message says why, for a person.
 */
final class RefusedUpload extends RuntimeException
{
}
