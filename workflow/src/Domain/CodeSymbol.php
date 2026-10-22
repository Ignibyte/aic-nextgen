<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

/**
 * One symbol in the code index (#106): a class-like, a function or a method
 * from PHP, or a route, command, job or module from the build's surface.
 */
final readonly class CodeSymbol
{
    /**
     * @param string $name   Its full name: `App\Note\Controller\NoteListPage`, `…::render`, `route:GET /notes`.
     * @param string $kind   `class`, `interface`, `trait`, `enum`, `function`, `method`, `route`, `command`, `job` or `module`.
     * @param string $file   The file it is in, relative to the project.
     * @param int    $line   Its line; 0 for a surface symbol.
     * @param string $area   The file's area: `module:<m>`, `system:<S>`, `generated`, `workflow`, `gates` or `project`.
     * @param string $detail A route's kind, a module's namespace; empty otherwise.
     */
    public function __construct(
        public string $name,
        public string $kind,
        public string $file,
        public int $line,
        public string $area,
        public string $detail = '',
    ) {
    }
}
