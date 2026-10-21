<?php

declare(strict_types=1);

namespace App\Faq\Service;

use App\Faq\Entity\FaqEntry;
use Override;

/**
 * Three questions about modules, written in code so every request, interpreted
 * or compiled, answers the same.
 */
final readonly class DemoFaqEntries implements FaqEntries
{
    /**
     * @return list<FaqEntry> The questions.
     */
    #[Override]
    public function all(): array
    {
        return [
            new FaqEntry('What is a module?', 'A folder under modules/ with a manifest and its classes, wired by the build.'),
            new FaqEntry('Who wires it?', 'bin/aic-build reads every manifest and writes the wiring under app/Generated/.'),
            new FaqEntry('Is anything discovered at run time?', 'No. The manifests are read at build time, so the app compiles natively.'),
        ];
    }
}
