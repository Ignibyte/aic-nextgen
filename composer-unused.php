<?php

declare(strict_types=1);

use ComposerUnused\ComposerUnused\Configuration\Configuration;
use ComposerUnused\ComposerUnused\Configuration\PatternFilter;

return static function (Configuration $config): Configuration {
    return $config
        ->addPatternFilter(PatternFilter::fromString('/^ext-.*/'));
};
