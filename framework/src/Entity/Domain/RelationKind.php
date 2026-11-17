<?php

declare(strict_types=1);

namespace AIC\Entity\Domain;

/**
 * How an entity type's rows relate to another's (#306).
 */
enum RelationKind
{
    /** Many target rows hold the owner's key in a column of theirs. */
    case HasMany;

    /** One target row holds the owner's key in a column of its own. */
    case HasOne;

    /** Target rows reached through a pivot type, whose rows hold both keys and fields of their own. */
    case ManyToMany;

    /** One row of one of a closed list of types, named by a type field and an id field on the owner. */
    case Polymorphic;
}
