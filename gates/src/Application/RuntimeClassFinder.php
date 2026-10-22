<?php

declare(strict_types=1);

namespace Gates\Application;

use RuntimeException;

use function file_get_contents;
use function sort;

/**
 * Lists the runtime corpus the native gate compiles (gate:21).
 *
 * The runtime is every class, interface, trait and enum under `framework/src`
 * (`AIC\`), `app` and `modules` (`App\`; a module's classes sit in its `src/`,
 * and its manifest declares no named class, so it is never probed, #92). The gate tooling (`gates/`, `Gates\`), the
 * entry scripts in `bin/` and the SAPI shell (`public/index.php`,
 * `bootstrap/serve.php`, which gate:23 compiles with the whole app) stay out;
 * keeping them outside the two runtime roots means no scope list is needed. A runtime class that must be left out
 * goes in here, with its reason.
 */
final readonly class RuntimeClassFinder
{
    /**
     * @param ClassReferenceScanner $classReferenceScanner Reads what each file declares.
     * @param PhpSourceCollector    $phpSourceCollector    Lists the `*.php` files under the runtime roots.
     * @param ProjectProfile        $projectProfile        The runtime roots, by the project's profile (#130).
     */
    public function __construct(
        private ClassReferenceScanner $classReferenceScanner = new ClassReferenceScanner(),
        private PhpSourceCollector $phpSourceCollector = new PhpSourceCollector(),
        private ProjectProfile $projectProfile = new ProjectProfile(),
    ) {
    }

    /**
     * Every runtime class-like FQCN, sorted.
     *
     * @param string $root Repo-root absolute path.
     *
     * @throws RuntimeException When a runtime file cannot be read.
     *
     * @return list<string> Runtime FQCNs.
     */
    public function find(string $root): array
    {
        return $this->classes($root, $this->projectProfile->roots($root)->runtimeRoots);
    }

    /**
     * Every class gate:23 compiles with the site's entry: the runtime, and in an app the framework under vendor/ (#130),
     * since bootstrap/serve.php names framework classes no app class reaches.
     *
     * @param string $root Repo-root absolute path.
     *
     * @throws RuntimeException When a file cannot be read.
     *
     * @return list<string> Their FQCNs, sorted.
     */
    public function forSite(string $root): array
    {
        return $this->classes($root, $this->projectProfile->roots($root)->siteRoots);
    }

    /**
     * @param string                             $root  Repo-root absolute path.
     * @param array<string, string|list<string>> $roots PSR-4 prefixes and their folders.
     *
     * @throws RuntimeException When a file cannot be read.
     *
     * @return list<string> The class-likes declared under them, sorted.
     */
    private function classes(string $root, array $roots): array
    {
        $classes = [];

        foreach ($this->phpSourceCollector->collect($root, $roots) as $file) {
            $declared = $this->declaredIn($file);

            if ($declared === null) {
                continue;
            }

            $classes[] = $declared;
        }

        sort($classes);

        return $classes;
    }

    /**
     * The FQCN a file declares, if any.
     *
     * @param string $file Absolute file path.
     *
     * @throws RuntimeException When the file cannot be read.
     *
     * @return string|null The declared FQCN.
     */
    private function declaredIn(string $file): ?string
    {
        $source = file_get_contents($file);

        if ($source === false) {
            throw new RuntimeException('gate:21 — cannot read ' . $file);
        }

        return $this->classReferenceScanner->scan($source)->declared;
    }
}
