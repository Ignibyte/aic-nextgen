<?php

declare(strict_types=1);

use Gates\Architecture\CoreArchitectureTest;
use Gates\Architecture\KindsArchitectureTest;
use Gates\Architecture\RenderingArchitectureTest;

// Actual tagged architecture services consumed by PHPStan.
return [
    'services' => [
        ['class' => CoreArchitectureTest::class, 'tags' => ['phpat.test']],
        ['class' => KindsArchitectureTest::class, 'tags' => ['phpat.test']],
        ['class' => RenderingArchitectureTest::class, 'tags' => ['phpat.test']],
    ],
];
