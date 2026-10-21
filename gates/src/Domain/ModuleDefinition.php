<?php

declare(strict_types=1);

namespace Gates\Domain;

use AIC\Module\Contracts\Module;

use function str_starts_with;
use function substr;

/**
 * One module as the build read it (#92): its name, the namespace its classes
 * live in, its folder, and its manifest.
 */
final readonly class ModuleDefinition
{
    /**
     * @param string $name      The module's name, its folder's name (`user_account`).
     * @param string $namespace Its classes' namespace prefix, `App\<StudlyName>\` (`App\UserAccount\`).
     * @param string $directory Its folder, absolute.
     * @param Module $module    Its manifest.
     */
    public function __construct(
        public string $name,
        public string $namespace,
        public string $directory,
        public Module $module,
    ) {
    }

    /**
     * @return string The short class prefix of its generated wiring (`UserAccount`).
     */
    public function studlyName(): string
    {
        return substr($this->namespace, 4, -1);
    }

    /**
     * @param string $class A class name.
     *
     * @return bool Whether it is in this module's namespace.
     */
    public function owns(string $class): bool
    {
        return str_starts_with($class, $this->namespace);
    }
}
