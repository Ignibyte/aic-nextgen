<?php

declare(strict_types=1);

namespace AIC\Profiler\Application;

use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Profiler\Domain\ComponentRecord;
use AIC\Profiler\Domain\StatementRecord;

/**
 * Reads one kept profile's lists for the profiler's detail page (#230), each
 * expanded by Postgres from the stored JSON in the order it was kept.
 */
final readonly class ProfileParts
{
    /** The statements, in order. */
    private const string STATEMENTS = "SELECT coalesce(s->>'sql', '') AS sql,"
        . " coalesce((s->>'microseconds')::bigint, 0) AS microseconds, coalesce((s->>'rows')::bigint, 0) AS row_count"
        . " FROM aic_profiles, jsonb_array_elements(profile::jsonb->'statements') WITH ORDINALITY AS e(s, n)"
        . ' WHERE id = ? ORDER BY n';

    /** The templates, in the order each finished. */
    private const string COMPONENTS = "SELECT coalesce(c->>'path', '') AS path, coalesce(c->>'component', '') AS component,"
        . " coalesce((c->>'microseconds')::bigint, 0) AS microseconds"
        . " FROM aic_profiles, jsonb_array_elements(profile::jsonb->'components') WITH ORDINALITY AS e(c, n)"
        . ' WHERE id = ? ORDER BY n';

    /** An object of strings, by name. */
    private const string TEXTS = 'SELECT key, value FROM aic_profiles,'
        . " jsonb_each_text(coalesce(profile::jsonb -> ?, '{}'::jsonb)) WHERE id = ? ORDER BY key";

    /** A list of names, in order. */
    private const string NAMES = 'SELECT value FROM aic_profiles,'
        . " jsonb_array_elements_text(coalesce(profile::jsonb -> ?, '[]'::jsonb)) WITH ORDINALITY AS e(value, n)"
        . ' WHERE id = ? ORDER BY n';

    /** The same browser's request just before. */
    private const string PREVIOUS = 'SELECT p.id FROM aic_profiles p, aic_profiles c WHERE c.id = ?'
        . " AND coalesce(c.profile::jsonb->>'browser', '') <> ''"
        . " AND p.profile::jsonb->>'browser' = c.profile::jsonb->>'browser'"
        . ' AND (p.created_at, p.id) < (c.created_at, c.id) ORDER BY p.created_at DESC, p.id DESC LIMIT 1';

    /** The newest whole-document answer for a path. */
    private const string FULL = "SELECT coalesce((profile::jsonb->>'bytes')::bigint, 0) AS bytes FROM aic_profiles"
        . " WHERE path = ? AND coalesce(profile::jsonb->'tessera'->>'Tessera-Request', '') = ''"
        . " AND coalesce(profile::jsonb->'answer'->>'Content-Type', '') LIKE 'text/html%'"
        . ' ORDER BY created_at DESC, id DESC LIMIT 1';

    /**
     * @param Database $database The database.
     * @param string   $id       A profile's id.
     *
     * @throws QueryException When the table cannot be read.
     *
     * @return list<StatementRecord> Its statements.
     */
    public function statements(Database $database, string $id): array
    {
        $statements = [];

        foreach ($database->fetchAll(self::STATEMENTS, [$id]) as $row) {
            $statements[] = new StatementRecord($row->string('sql'), $row->int('microseconds'), $row->int('row_count'));
        }

        return $statements;
    }

    /**
     * @param Database $database The database.
     * @param string   $id       A profile's id.
     *
     * @throws QueryException When the table cannot be read.
     *
     * @return list<ComponentRecord> Its templates.
     */
    public function components(Database $database, string $id): array
    {
        $components = [];

        foreach ($database->fetchAll(self::COMPONENTS, [$id]) as $row) {
            $components[] = new ComponentRecord($row->string('path'), $row->string('component'), $row->int('microseconds'));
        }

        return $components;
    }

    /**
     * @param Database $database The database.
     * @param string   $id       A profile's id.
     * @param string   $member   An object member of strings: `tessera` or `answer`.
     *
     * @throws QueryException When the table cannot be read.
     *
     * @return array<string, string> Its strings, by name.
     */
    public function texts(Database $database, string $id, string $member): array
    {
        $texts = [];

        foreach ($database->fetchAll(self::TEXTS, [$member, $id]) as $row) {
            $texts[$row->string('key')] = $row->string('value');
        }

        return $texts;
    }

    /**
     * @param Database $database The database.
     * @param string   $id       A profile's id.
     * @param string   $member   A list member of strings: `headers` or `fields`.
     *
     * @throws QueryException When the table cannot be read.
     *
     * @return list<string> Its names, in order.
     */
    public function names(Database $database, string $id, string $member): array
    {
        $names = [];

        foreach ($database->fetchAll(self::NAMES, [$member, $id]) as $row) {
            $names[] = $row->string('value');
        }

        return $names;
    }

    /**
     * @param Database $database The database.
     * @param string   $id       A profile's id.
     *
     * @throws QueryException When the table cannot be read.
     *
     * @return string The id of the same browser's request just before it; empty for none.
     */
    public function previous(Database $database, string $id): string
    {
        $row = $database->fetchAll(self::PREVIOUS, [$id])->first();

        return $row instanceof Row
            ? $row->string('id')
            : '';
    }

    /**
     * @param Database $database The database.
     * @param string   $path     A path.
     *
     * @throws QueryException When the table cannot be read.
     *
     * @return int The newest whole-document answer for it, in bytes; 0 for none.
     */
    public function fullBytes(Database $database, string $path): int
    {
        $row = $database->fetchAll(self::FULL, [$path])->first();

        return $row instanceof Row
            ? $row->int('bytes')
            : 0;
    }
}
