<?php

declare(strict_types=1);

namespace {namespace}\Entity;

use AIC\Entity\Domain\EntityType;
use AIC\Entity\Domain\Field;
use AIC\Entity\Domain\FieldType;
use InvalidArgumentException;

/**
 * The {module} module's entity types, one accessor per table, made by bin/aic make:entity and given their relations by
 * bin/aic make:relation (#328). A relation's target is built without relations of its own, so no type embeds itself.
 */
final readonly class {types}
{
{accessors}
{tables}}
