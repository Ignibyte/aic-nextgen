<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Make;

use function array_filter;
use function array_values;

/**
 * What one make asks for (#122): the module, what it names for the record,
 * the files it writes, the manifest call (none for `make:module`, which
 * writes the manifest), what it claims (routes, commands, jobs, templates or the selected theme)
 * so the planner can refuse a collision, whether it needs `site`, the layout
 * files of which one must be there (`--layout`, #157), the routes some
 * manifest must declare already (a page action's page, #322), and the lines
 * it prints after writing.
 */
final readonly class MakeRecipe
{
    /**
     * @param string            $module      The module; empty for a component, which lives in the theme (#153).
     * @param string            $target      What it made, for the record and the answer.
     * @param list<MadeFile>    $files       The files it writes.
     * @param ManifestCall|null $call        The call it adds; null when it edits no manifest.
     * @param list<string>      $claims      What no other manifest may declare.
     * @param bool              $needsSite   Whether the module must require `site`.
     * @param string            $component   A component file that must exist (`--component`); empty for none.
     * @param bool              $makesModule Whether it is `make:module`, which writes the manifest itself.
     * @param list<string>      $lines       What else to do by hand, printed after the files (#153).
     * @param list<string>      $layouts     The files that ship a named layout (`--layout`, #157); one must be there.
     * @param list<string>      $routes      Claims some manifest must declare already, such as `route GET /notes` (#322).
     */
    public function __construct(
        public string $module,
        public string $target,
        public array $files,
        public ?ManifestCall $call,
        public array $claims = [],
        public bool $needsSite = false,
        public string $component = '',
        public bool $makesModule = false,
        public array $lines = [],
        public array $layouts = [],
        public array $routes = [],
    ) {
    }

    /**
     * @return string The module's manifest, relative to the project's root; empty for a make that names no module.
     */
    public function manifest(): string
    {
        return $this->module === ''
            ? ''
            : 'modules/' . $this->module . '/' . $this->module . '.module.php';
    }

    /**
     * @return list<MadeFile> Its files, those it creates first, then those it edits in place (#328).
     */
    public function ordered(): array
    {
        return [
            ...array_values(array_filter($this->files, static fn (MadeFile $madeFile): bool => $madeFile->created())),
            ...array_values(array_filter($this->files, static fn (MadeFile $madeFile): bool => !$madeFile->created())),
        ];
    }
}
