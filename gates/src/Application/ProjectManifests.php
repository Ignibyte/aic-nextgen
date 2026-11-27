<?php

declare(strict_types=1);

namespace Gates\Application;

use function array_map;

/**
 * The composer manifests the composer gates check: the root and the framework.
 *
 * The root `composer.json` is the demo app, `aic/app`; `framework/composer.json`
 * is `ignibyte/aic-framework`, path-installed into the root. Normalize (gate:1), the
 * require allow-list (gate:2) and the require-checker (gate:3) all read this
 * one list, so a third manifest is added in one place. An interpreted
 * worker's manifest (#335) is normalized too, but its `require` is its own:
 * only `normalized()` names it.
 */
final readonly class ProjectManifests
{
    /**
     * @param ProjectProfile $projectProfile The manifests, by the project's profile (#130).
     */
    public function __construct(
        private ProjectProfile $projectProfile = new ProjectProfile(),
    ) {
    }

    /**
     * List the manifest paths, root first.
     *
     * @param string $root Repo-root absolute path.
     *
     * @return list<string>
     */
    public function paths(string $root): array
    {
        return array_map(static fn (string $manifest): string => $root . '/' . $manifest, $this->projectProfile->roots($root)->manifests);
    }

    /**
     * List the manifests gate:1 normalizes: these, then each worker's.
     *
     * @param string $root Repo-root absolute path.
     *
     * @return list<string>
     */
    public function normalized(string $root): array
    {
        return [
            ...$this->paths($root),
            ...array_map(
                static fn (string $worker): string => $root . '/' . $worker . '/composer.json',
                $this->projectProfile->workers($root),
            ),
        ];
    }
}
