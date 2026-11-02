<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\NativeSiteAnswer;
use Gates\Domain\NativeSiteStep;
use PDO;
use PDOException;

use function array_keys;
use function is_string;
use function sprintf;
use function substr;

/**
 * Compares what PHP and the compiled site recorded for each profiled step
 * (#234): the two profiles their `X-AIC-Profile` headers name, reduced by
 * Postgres to their shape. The shape is the handler, kind, status, method,
 * path and query, the Tessera headers asked and answered, the field names,
 * each statement's SQL and each template's path and class, one text column a
 * member. Times, ids and the browser key are left out.
 */
final readonly class NativeSiteProfiles
{
    /** One profile's shape, a row per member in the order they are compared: the member, then its text. */
    private const string SHAPE = 'SELECT m.member, m.value FROM (SELECT profile::jsonb AS p FROM aic_profiles WHERE id = ?) AS kept,'
        . " LATERAL (VALUES (1, 'handler', p->>'handler'), (2, 'kind', p->>'kind'), (3, 'status', p->>'status'),"
        . " (4, 'method', p->>'method'), (5, 'path', p->>'path'), (6, 'query', p->>'query'),"
        . " (7, 'tessera', (p->'tessera')::text), (8, 'answer', (p->'answer')::text), (9, 'fields', (p->'fields')::text),"
        . " (10, 'statements', (SELECT coalesce(jsonb_agg(s->'sql' ORDER BY n), '[]'::jsonb)::text"
        . " FROM jsonb_array_elements(p->'statements') WITH ORDINALITY AS e(s, n))),"
        . " (11, 'components', (SELECT coalesce(jsonb_agg(jsonb_build_array(c->'path', c->'component') ORDER BY n), '[]'::jsonb)::text"
        . " FROM jsonb_array_elements(p->'components') WITH ORDINALITY AS e(c, n)))) AS m(n, member, value) ORDER BY m.n";

    /**
     * @param list<NativeSiteStep>   $steps  The profiled steps.
     * @param list<NativeSiteAnswer> $php    PHP's answers, in step order.
     * @param list<NativeSiteAnswer> $native The compiled site's answers, in step order.
     * @param array<string, string>  $env    The environment, with the database's `PG*`.
     *
     * @return list<string> Each step whose two profiles differ in shape, or lack one, with the first difference.
     */
    public function differences(array $steps, array $php, array $native, array $env): array
    {
        try {
            $pdo = self::connected($env);
        } catch (PDOException $pdoException) {
            return ['profiles: the database cannot be read: ' . $pdoException->getMessage()];
        }

        $differences = [];

        foreach ($steps as $index => $step) {
            $phpShape = self::shape($pdo, self::profileId($php[$index] ?? null));
            $nativeShape = self::shape($pdo, self::profileId($native[$index] ?? null));
            $difference = self::difference($phpShape, $nativeShape);

            if ($difference === '') {
                continue;
            }

            $differences[] = sprintf('%s (%s %s): profile %s', $step->name, $step->method, $step->path, $difference);
        }

        return $differences;
    }

    /**
     * @param NativeSiteAnswer|null $nativeSiteAnswer An answer, or none.
     *
     * @return string The profile id it carried; empty for none.
     */
    private static function profileId(?NativeSiteAnswer $nativeSiteAnswer): string
    {
        return $nativeSiteAnswer instanceof NativeSiteAnswer
            ? $nativeSiteAnswer->httpExchange->profile
            : '';
    }

    /**
     * @param array<string, string> $php    PHP's profile shape; empty when it kept none.
     * @param array<string, string> $native The compiled site's; empty when it kept none.
     *
     * @return string The first difference; empty when they match.
     */
    private static function difference(array $php, array $native): string
    {
        if ($php === [] || $native === []) {
            return sprintf('missing: PHP %s, native %s', self::kept($php), self::kept($native));
        }

        foreach ($php as $member => $value) {
            if ($value !== ($native[$member] ?? '')) {
                return sprintf('%s differs: PHP %s, native %s', $member, substr($value, 0, 160), substr($native[$member] ?? '', 0, 160));
            }
        }

        return '';
    }

    /**
     * @param array<string, string> $shape A profile's shape.
     *
     * @return string Whether the site kept a profile.
     */
    private static function kept(array $shape): string
    {
        return $shape === []
            ? 'none'
            : 'kept';
    }

    /**
     * @param PDO    $pdo The database.
     * @param string $id  A profile's id; empty for none.
     *
     * @throws PDOException When the query fails.
     *
     * @return array<string, string> Its shape by member; empty when there is no such profile, an empty id included.
     */
    private static function shape(PDO $pdo, string $id): array
    {
        $statement = $pdo->prepare(self::SHAPE);

        if ($statement === false) {
            return [];
        }

        $statement->execute([$id]);
        $pairs = $statement->fetchAll(PDO::FETCH_KEY_PAIR);
        $shape = [];

        foreach (array_keys($pairs) as $member) {
            $shape[(string) $member] = is_string($pairs[$member])
                ? $pairs[$member]
                : '';
        }

        return $shape;
    }

    /**
     * @param array<string, string> $env The environment.
     *
     * @throws PDOException When the database cannot be reached.
     *
     * @return PDO A connection to the app's database.
     */
    private static function connected(array $env): PDO
    {
        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            $env['PGHOST'] ?? '127.0.0.1',
            $env['PGPORT'] ?? '5432',
            $env['PGDATABASE'] ?? 'db',
        );

        return new PDO($dsn, $env['PGUSER'] ?? 'db', $env['PGPASSWORD'] ?? 'db', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }
}
