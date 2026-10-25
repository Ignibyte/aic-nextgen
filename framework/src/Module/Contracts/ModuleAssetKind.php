<?php

declare(strict_types=1);

namespace AIC\Module\Contracts;

/** The declared publication role of an asset: bundled CSS, a script, or another public file. */
enum ModuleAssetKind
{
    /** CSS published individually and included in the application's combined stylesheet. */
    case Style;

    /** JavaScript published without changing its bytes or automatically linking it into every page. */
    case Script;

    /** A supported image, font, map or data file published without changing its bytes. */
    case File;
}
