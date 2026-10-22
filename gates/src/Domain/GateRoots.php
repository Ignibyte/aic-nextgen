<?php

declare(strict_types=1);

namespace Gates\Domain;

use function is_string;

/**
 * What the gates read in a project, by its profile (#130): the class roots
 * PHPMD reads, the source roots phpcpd compares, the runtime roots (gates 16,
 * 21, 27), the manifests (gates 1 to 3), the folders whose shell scripts
 * gate:18 reads, and the paths gate:26 admits outside `modules/`. An app's
 * are its shape; AIC's add the framework, the workflow and the gates.
 */
final readonly class GateRoots
{
    /**
     * @param list<string>                       $classRoots    PHPMD's paths (gate:10).
     * @param list<string>                       $sourceRoots   phpcpd's paths (gate:14).
     * @param array<string, string|list<string>> $runtimeRoots  Each runtime namespace's folders (gates 16, 21, 27).
     * @param array<string, string|list<string>> $siteRoots     What gate:23 compiles with serve.php: the app's runtime
     *                                                          and the framework's, from vendor/ in an app.
     * @param list<string>                       $manifests     The composer.json files, relative to the root (gates 1 to 3).
     * @param list<string>                       $scriptFolders The folders whose `*.sh` gate:18 reads.
     * @param list<string>                       $shell         What may sit outside `modules/`, as `fnmatch` patterns (gate:26).
     */
    public function __construct(
        public array $classRoots,
        public array $sourceRoots,
        public array $runtimeRoots,
        public array $siteRoots,
        public array $manifests,
        public array $scriptFolders,
        public array $shell,
    ) {
    }

    /**
     * @return list<string> Every runtime folder, the namespaces' order kept.
     */
    public function runtimeFolders(): array
    {
        $folders = [];

        foreach ($this->runtimeRoots as $runtimeRoot) {
            $folders = [...$folders, ...(is_string($runtimeRoot) ? [$runtimeRoot] : $runtimeRoot)];
        }

        return $folders;
    }
}
