<?php

declare(strict_types=1);

namespace Gates\Domain;

use AIC\Module\Contracts\ModuleDeclaration;
use AIC\Module\Contracts\ModuleEntry;
use AIC\Module\Contracts\ModuleManifest;
use AIC\Module\Contracts\ModuleSection;

use function str_starts_with;
use function substr;

/**
 * One module as the build read it (#92): its name, the namespace its classes
 * live in, and its manifest, read by declaration class or by section.
 */
final readonly class ModuleDefinition
{
    /**
     * @param string         $name           The module's name, its folder's name (`user_account`).
     * @param string         $namespace      Its classes' namespace prefix, `App\<StudlyName>\` (`App\UserAccount\`).
     * @param ModuleManifest $moduleManifest Its manifest.
     */
    public function __construct(
        public string $name,
        public string $namespace,
        private ModuleManifest $moduleManifest,
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

    /**
     * @template T of ModuleDeclaration
     *
     * @param class-string<T> $type A declaration class.
     *
     * @return list<T> The manifest's declarations of it, in the order declared.
     */
    public function declared(string $type): array
    {
        $declared = [];

        foreach ($this->moduleManifest->declarations as $moduleDeclaration) {
            if (!($moduleDeclaration instanceof $type)) {
                continue;
            }

            $declared[] = $moduleDeclaration;
        }

        return $declared;
    }

    /**
     * @param ModuleSection $moduleSection A section of bare names.
     *
     * @return list<string> Its names, in the order declared.
     */
    public function entries(ModuleSection $moduleSection): array
    {
        $entries = [];

        foreach ($this->declared(ModuleEntry::class) as $moduleEntry) {
            if ($moduleEntry->moduleSection !== $moduleSection) {
                continue;
            }

            $entries[] = $moduleEntry->value;
        }

        return $entries;
    }
}
