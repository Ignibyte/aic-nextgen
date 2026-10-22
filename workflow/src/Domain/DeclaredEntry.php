<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

/**
 * One row of a spec's `## Entry Points` (#101): an entry point the plan
 * declares, with its kind. Method, status and example apply to routes only.
 */
final readonly class DeclaredEntry
{
    /**
     * @param string $kind    Page, Api, Stream, Command or Job.
     * @param string $method  A route's method, such as `GET`; empty for a command or a job.
     * @param string $name    A route's path, or a command's or a job's name.
     * @param string $class   The class that handles it.
     * @param string $module  The module; empty for a command or a job.
     * @param int    $status  The status a route answers with; 200 when not declared.
     * @param string $example A concrete path for a route with `{param}`; empty otherwise.
     */
    public function __construct(
        public string $kind,
        public string $method,
        public string $name,
        public string $class,
        public string $module,
        public int $status,
        public string $example,
    ) {
    }

    /**
     * @return string The key the surface holds it by: `GET /path`, or the name.
     */
    public function key(): string
    {
        return $this->method === ''
            ? $this->name
            : $this->method . ' ' . $this->name;
    }
}
