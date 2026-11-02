<?php

declare(strict_types=1);

namespace AIC\Profiler\Application;

use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Profiler\Domain\ProfileFilter;
use AIC\Profiler\Domain\ProfileRow;
use AIC\Profiler\Domain\ProfileTimes;
use AIC\Profiler\Domain\ProfileView;
use AIC\Profiler\Domain\RequestDetail;

/**
 * Reads the kept profiles for the profiler's pages (#230). Postgres reads the
 * stored JSON (`profile::jsonb`), so each value reaches PHP as a typed column
 * and no profile is decoded here.
 */
final readonly class ProfileReader
{
    /** Each list column, read from the row and its JSON. */
    private const string ROWS = "SELECT id, to_char(created_at, 'HH24:MI:SS') AS kept, method, path, status, microseconds,"
        . " coalesce(profile::jsonb->>'kind', '') AS kind,"
        . " coalesce(profile::jsonb->'tessera'->>'Tessera-Request', '') AS tessera,"
        . " coalesce(profile::jsonb->'tessera'->>'Tessera-Targets', '') AS targets,"
        . " coalesce(jsonb_array_length(profile::jsonb->'statements'), 0) AS statements,"
        . " coalesce((profile::jsonb->>'bytes')::bigint, 0) AS bytes FROM aic_profiles";

    /** The newest 100 that pass the filter. */
    private const string LIST = self::ROWS . " WHERE (? = '' OR position(? in path) > 0)"
        . " AND (? = '' OR profile::jsonb->>'kind' = ?) AND (? = 0 OR status = ?)"
        . ' ORDER BY created_at DESC, id DESC LIMIT 100';

    /** One profile's own members. */
    private const string VIEW = "SELECT coalesce(profile::jsonb->>'handler', '') AS handler,"
        . " coalesce(profile::jsonb->>'user', '') AS user_id,"
        . " coalesce((profile::jsonb->'microseconds'->>'middleware')::bigint, 0) AS middleware,"
        . " coalesce((profile::jsonb->'microseconds'->>'handler')::bigint, 0) AS handled,"
        . " coalesce((profile::jsonb->'microseconds'->>'render')::bigint, 0) AS render,"
        . " coalesce(profile::jsonb->>'body', '') AS body, coalesce(profile::jsonb->>'query', '') AS query"
        . ' FROM aic_profiles WHERE id = ?';

    /**
     * @param Database     $database     The database.
     * @param ProfileParts $profileParts Reads a profile's lists.
     */
    public function __construct(
        private Database $database,
        private ProfileParts $profileParts = new ProfileParts(),
    ) {
    }

    /**
     * @param ProfileFilter $profileFilter What to keep.
     *
     * @throws QueryException When the table cannot be read.
     *
     * @return list<ProfileRow> The newest 100 that pass, newest first.
     */
    public function rows(ProfileFilter $profileFilter): array
    {
        $rows = $this->database->fetchAll(self::LIST, [
            $profileFilter->path,
            $profileFilter->path,
            $profileFilter->kind,
            $profileFilter->kind,
            $profileFilter->status,
            $profileFilter->status,
        ]);
        $profileRows = [];

        foreach ($rows as $row) {
            $profileRows[] = $this->row($row);
        }

        return $profileRows;
    }

    /**
     * @param string $id A profile's id.
     *
     * @throws QueryException When the table cannot be read.
     *
     * @return ProfileView|null The profile; null when none has that id.
     */
    public function view(string $id): ?ProfileView
    {
        $listed = $this->database->fetchAll(self::ROWS . ' WHERE id = ?', [$id])->first();
        $own = $this->database->fetchAll(self::VIEW, [$id])->first();

        if (!$listed instanceof Row || !$own instanceof Row) {
            return null;
        }

        $profileRow = $this->row($listed);
        $profileTimes = new ProfileTimes($profileRow->microseconds, $own->int('middleware'), $own->int('handled'), $own->int('render'));

        return new ProfileView(
            $profileRow,
            $own->string('handler'),
            $own->string('user_id'),
            $profileTimes,
            $this->profileParts->statements($this->database, $id),
            $this->profileParts->components($this->database, $id),
            $this->profileParts->texts($this->database, $id, 'tessera'),
            $this->profileParts->texts($this->database, $id, 'answer'),
            $this->detail($id, $profileRow->kind, $own),
            $this->profileParts->previous($this->database, $id),
            $this->profileParts->fullBytes($this->database, $profileRow->path),
        );
    }

    /**
     * @param string $id   The profile's id.
     * @param string $kind The route's kind.
     * @param Row    $row  The profile's own members.
     *
     * @throws QueryException When the table cannot be read.
     *
     * @return RequestDetail Its header and field names, query and Tessera markup.
     */
    private function detail(string $id, string $kind, Row $row): RequestDetail
    {
        return new RequestDetail(
            $kind,
            '',
            $this->profileParts->names($this->database, $id, 'headers'),
            $this->profileParts->names($this->database, $id, 'fields'),
            $row->string('body'),
            $row->string('query'),
        );
    }

    /**
     * @param Row $row A row of the list columns.
     *
     * @throws QueryException When a column is missing or of another type.
     *
     * @return ProfileRow It, typed.
     */
    private function row(Row $row): ProfileRow
    {
        return new ProfileRow(
            $row->string('id'),
            $row->string('kept'),
            $row->string('method'),
            $row->string('path'),
            $row->int('status'),
            $row->int('microseconds'),
            $row->string('kind'),
            $row->string('tessera'),
            $row->string('targets'),
            $row->int('statements'),
            $row->int('bytes'),
        );
    }
}
