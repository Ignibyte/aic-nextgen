<?php

declare(strict_types=1);

/*
 * The {module} module's manifest, made by bin/aic make:module (#122). The build
 * reads it; nothing reads it at run time.
 */

use AIC\Module\Contracts\ModuleManifest;

return (new ModuleManifest('{module}')){requires};
