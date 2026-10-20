<?php

declare(strict_types=1);

namespace AIC\Form\Exception;

use RuntimeException;

/**
 * A signed envelope was missing, malformed or altered: its values cannot be
 * trusted.
 */
final class InvalidEnvelope extends RuntimeException
{
}
