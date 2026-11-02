<?php

declare(strict_types=1);

namespace AIC\Profiler\Application;

use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use AIC\Profiler\Domain\ComponentRecord;
use AIC\Profiler\Domain\ProfileJson;
use AIC\Profiler\Domain\ProfileTimes;
use AIC\Profiler\Domain\RequestFacts;
use AIC\Profiler\Domain\StatementRecord;

use function bin2hex;
use function random_bytes;

/**
 * Keeps a profile in `aic_profiles` (#229) through the database as the root
 * built it, not the profiled one, so its own statements are never recorded;
 * then deletes all but the newest 500. A profile that cannot be kept (the
 * table not migrated yet) is dropped: the request it describes still answers.
 */
final readonly class ProfileWriter
{
    /** Deletes all but the newest 500 profiles. */
    private const string PRUNE = 'DELETE FROM aic_profiles WHERE id IN'
        . ' (SELECT id FROM aic_profiles ORDER BY created_at DESC, id DESC OFFSET 500)';

    /**
     * @param Database    $database    The database, unprofiled.
     * @param ProfileJson $profileJson Encodes the profile.
     */
    public function __construct(
        private Database $database,
        private ProfileJson $profileJson = new ProfileJson(),
    ) {
    }

    /**
     * @param RequestFacts          $requestFacts The request and its answer.
     * @param ProfileTimes          $profileTimes Where its time went.
     * @param list<StatementRecord> $statements   Its statements.
     * @param list<ComponentRecord> $components   Its templates.
     *
     * @return string The new profile's id; empty when it could not be kept.
     */
    public function store(
        RequestFacts $requestFacts,
        ProfileTimes $profileTimes,
        array $statements,
        array $components,
    ): string {
        $id = bin2hex(random_bytes(16));

        try {
            $profile = $this->profileJson->encode($requestFacts, $profileTimes, $statements, $components);
            $this->insert($id, $requestFacts, $profileTimes, $profile);
            $this->database->execute(self::PRUNE, []);
        } catch (QueryException) {
            return '';
        }

        return $id;
    }

    /**
     * @param string       $id           The profile's id.
     * @param RequestFacts $requestFacts The request and its answer.
     * @param ProfileTimes $profileTimes Where its time went.
     * @param string       $profile      The whole profile, as JSON.
     *
     * @throws QueryException When the row cannot be written.
     */
    private function insert(string $id, RequestFacts $requestFacts, ProfileTimes $profileTimes, string $profile): void
    {
        $this->database->execute(
            'INSERT INTO aic_profiles (id, created_at, method, path, status, microseconds, profile)'
                . ' VALUES (?, now(), ?, ?, ?, ?, ?)',
            [$id, $requestFacts->method, $requestFacts->path, $requestFacts->status, $profileTimes->total, $profile],
        );
    }
}
