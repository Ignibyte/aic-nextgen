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
 * (`AIC\`) and `app` (`App\`). The gate tooling (`gates/`, `Gates\`), the
 * entry scripts in `bin/` and the SAPI shell `public/worker.php` run
 * interpreted by design and stay out; keeping them outside the two runtime
 * roots means no scope list is needed. A runtime class that must be left out
 * goes in here, with its reason.
 *
 * @api
 */
final readonly class RuntimeClassFinder
{
    /**
     * The runtime roots, as PSR-4 prefix => directory relative to the repo root.
     */
    private const array RUNTIME_ROOTS = ['AIC\\' => 'framework/src', 'App\\' => 'app'];

    /**
     * @param ClassReferenceScanner $classReferenceScanner Reads what each file declares.
     * @param PhpSourceCollector    $phpSourceCollector    Lists the `*.php` files under the runtime roots.
     */
    public function __construct(
        private ClassReferenceScanner $classReferenceScanner = new ClassReferenceScanner(),
        private PhpSourceCollector $phpSourceCollector = new PhpSourceCollector(),
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
        $classes = [];

        foreach ($this->phpSourceCollector->collect($root, self::RUNTIME_ROOTS) as $file) {
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
