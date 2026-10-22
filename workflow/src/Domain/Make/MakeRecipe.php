<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Make;

/**
 * What one make asks for (#122): the module, what it names for the record,
 * the files it writes, the manifest call (none for `make:module`, which
 * writes the manifest, and for a component it does not export), what it claims (`route GET /x`, `command x`, `job x`)
 * so the planner can refuse a collision, whether it needs `site`, and the
 * lines it prints after writing.
 */
final readonly class MakeRecipe
{
    /**
     * @param string            $module      The module.
     * @param string            $target      What it made, for the record and the answer.
     * @param list<MadeFile>    $files       The files it writes.
     * @param ManifestCall|null $call        The call it adds; null when it edits no manifest.
     * @param list<string>      $claims      What no other manifest may declare.
     * @param bool              $needsSite   Whether the module must require `site`.
     * @param string            $component   A component file that must exist (`--component`); empty for none.
     * @param bool              $makesModule Whether it is `make:module`, which writes the manifest itself.
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
    ) {
    }

    /**
     * @return string The module's manifest, relative to the project's root.
     */
    public function manifest(): string
    {
        return 'modules/' . $this->module . '/' . $this->module . '.module.php';
    }
}
