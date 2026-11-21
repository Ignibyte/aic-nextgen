<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Make;

/**
 * Where one `make:export` line puts its export (#323): the path it is posted
 * to, the two jobs, the table and its view, how many days an export is kept
 * and the migration's version.
 */
final readonly class ExportPlace
{
    /**
     * @param string $path    The path it is posted to; downloads are under it.
     * @param string $job     The job that writes an export, such as `notes-export`.
     * @param string $table   The exports' table, such as `notes_exports`.
     * @param string $states  The view saying which are past their days.
     * @param string $days    How many days an export is kept, 1 to 365.
     * @param string $version The migration's version.
     */
    public function __construct(
        public string $path,
        public string $job,
        public string $table,
        public string $states,
        public string $days,
        public string $version,
    ) {
    }

    /**
     * @return string The job that deletes the exports past their days, also its schedule's name.
     */
    public function prune(): string
    {
        return $this->job . '-prune';
    }

    /**
     * @return string The download's route.
     */
    public function download(): string
    {
        return $this->path . '/{id:uuid}';
    }

    /**
     * @return array<string, string> The templates' placeholders it fills.
     */
    public function values(): array
    {
        return [
            'days' => $this->days,
            'folder' => 'exports/' . $this->job . '/',
            'job' => $this->job,
            'name' => $this->job . '.csv',
            'path' => $this->path,
            'prune' => $this->prune(),
            'states' => $this->states,
            'table' => $this->table,
            'version' => $this->version,
        ];
    }
}
