<?php

declare(strict_types=1);

namespace Gates\Domain;

/** A public asset's role (#153), from its file's extension: stylesheets join `aic.css`, the rest are copied. */
enum AssetKind
{
    case Style;
    case Script;
    case File;
}
