<?php

declare(strict_types=1);

namespace AIC\Entity\Domain;

/**
 * What a write did to a row of an observed entity type (#309), as its audit
 * row and its listeners' jobs name it.
 */
enum EntityEvent: string
{
    /** An insert. */
    case Created = 'created';

    /** An update; its audit row holds the written values. */
    case Updated = 'updated';

    /** A delete, or for a soft-deleting type the stamp of its deleted field. */
    case Deleted = 'deleted';

    /** A soft-deleted row brought back. */
    case Restored = 'restored';

    /** A row deleted for good, soft-deleted or not. */
    case Purged = 'purged';

    /** A state machine's transition (#308); its audit row holds the state entered. */
    case Transitioned = 'transitioned';
}
