<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

/**
 * One entry point as `build/surface.json` lists it (#101): a route, a command
 * or a job, with the kind its class implements.
 */
final readonly class SurfaceEntry
{
    /**
     * @param string $key    `GET /path` for a route, the name for a command or a job.
     * @param string $kind   Page, Api, Stream, Command or Job; `none` when the class is no entry point.
     * @param string $class  The handler.
     * @param string $module A route's module; empty for a command or a job.
     */
    public function __construct(
        public string $key,
        public string $kind,
        public string $class,
        public string $module,
    ) {
    }

    /**
     * @return string It as one line, for messages.
     */
    public function line(): string
    {
        return $this->key . ' (' . $this->kind . ', ' . $this->class . ($this->module === '' ? '' : ', module ' . $this->module) . ')';
    }
}
