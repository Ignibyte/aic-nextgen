<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Wiki;

use AIC\Workflow\Exception\WorkflowRefusal;

use function array_key_exists;
use function sprintf;
use function str_starts_with;
use function substr;

/**
 * Where a wiki page links (#108), relative to the page: other module pages,
 * project files, and the architecture pages AIC ships (#107), whose kind
 * headings and system sections are read from the pages themselves. A module
 * page sits three folders below the root (`docs/wiki/modules/`), the index
 * two (`docs/wiki/`).
 */
final readonly class WikiLinks
{
    /**
     * A namespace `systems.md` has no section for.
     */
    private const string NO_SECTION = '%s/systems.md has no section naming AIC\\%s; the wiki links each system to its section.';

    /**
     * @param string                $docs    The architecture pages' folder, relative to the root: `framework/docs`, or
     *                                       `vendor/ignibyte/aic-framework/docs` in an app.
     * @param array<string, string> $kinds   Each kind with a heading in `kinds.md`, and its anchor.
     * @param array<string, string> $systems Each framework namespace, such as `Http`, and its section's anchor in `systems.md`.
     */
    public function __construct(
        private string $docs,
        private array $kinds,
        private array $systems,
    ) {
    }

    /**
     * @param string $path  A project file, relative to the root.
     * @param bool   $index Whether the link sits on the index page.
     *
     * @return string Its link from the page.
     */
    public function file(string $path, bool $index): string
    {
        return ($index ? '../../' : '../../../') . $path;
    }

    /**
     * @param string $module A module.
     * @param bool   $index  Whether the link sits on the index page.
     *
     * @return string Its page from the page.
     */
    public function module(string $module, bool $index): string
    {
        return ($index ? 'modules/' : '') . $module . '.md';
    }

    /**
     * @param string $kind A kind, such as `Page`.
     *
     * @return string Its section of `kinds.md` from a module page; empty when `kinds.md` has no heading for it.
     */
    public function kind(string $kind): string
    {
        return array_key_exists($kind, $this->kinds)
            ? $this->file($this->docs . '/kinds.md', false) . '#' . $this->kinds[$kind]
            : '';
    }

    /**
     * @param string $area A framework area, `system:<Namespace>`.
     *
     * @throws WorkflowRefusal When `systems.md` has no section for the namespace.
     *
     * @return string Its section of `systems.md` from a module page.
     */
    public function system(string $area): string
    {
        $namespace = str_starts_with($area, 'system:')
            ? substr($area, 7)
            : $area;

        if (!array_key_exists($namespace, $this->systems)) {
            throw new WorkflowRefusal(sprintf(self::NO_SECTION, $this->docs, $namespace));
        }

        return $this->file($this->docs . '/systems.md', false) . '#' . $this->systems[$namespace];
    }

    /**
     * @return string The architecture pages' README from the index.
     */
    public function architecture(): string
    {
        return $this->file($this->docs . '/README.md', true);
    }
}
