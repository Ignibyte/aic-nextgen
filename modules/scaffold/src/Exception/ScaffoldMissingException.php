<?php

declare(strict_types=1);

namespace App\Scaffold\Exception;

use LogicException;

/**
 * Scaffold Missing: an exception of the module, made by bin/aic make:exception
 * (#319). Nothing throws it until the module does; it is API for it.
 *
 * @api
 */
final class ScaffoldMissingException extends LogicException
{
}
