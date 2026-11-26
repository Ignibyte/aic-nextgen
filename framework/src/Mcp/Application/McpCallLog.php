<?php

declare(strict_types=1);

namespace AIC\Mcp\Application;

use AIC\Access\Domain\Actor;
use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Database\Contracts\Rows;
use AIC\Mcp\Domain\CallOutcome;
use AIC\Mcp\Domain\CapabilityCall;
use AIC\Mcp\Domain\CapabilityResult;
use AIC\Mcp\Domain\McpProtocolException;

use function hash;
use function json_encode;

/**
 * The capability registry's audit and call ids (#332), in `aic_mcp_calls`:
 * the lock a call id is applied under, the stored answer of a call id already
 * used, a row for every call, and the latest rows. A caller is its client and
 * its person, so two callers' ids never meet.
 */
final readonly class McpCallLog
{
    /**
     * @param Database $database The database.
     */
    public function __construct(
        private Database $database,
    ) {
    }

    /**
     * Waits for the call id's lock and holds it to the end of the open transaction.
     *
     * @param Actor          $actor          Who calls.
     * @param CapabilityCall $capabilityCall The call, with its id.
     *
     * @throws QueryException When the lock cannot be taken.
     */
    public function lock(Actor $actor, CapabilityCall $capabilityCall): void
    {
        $name = 'aic-mcp:' . self::key($actor, $capabilityCall);
        $this->database->execute('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [$name]);
    }

    /**
     * @param Actor          $actor          Who calls.
     * @param CapabilityCall $capabilityCall The call, with its id.
     *
     * @throws QueryException       When the calls cannot be read.
     * @throws McpProtocolException When the id names a call of another tool (-32602).
     *
     * @return list<CapabilityResult> The earlier call's answer, replayed; none when the id is new.
     */
    public function stored(Actor $actor, CapabilityCall $capabilityCall): array
    {
        $rows = $this->database->fetchAll(
            'SELECT tool, outcome, output FROM aic_mcp_calls WHERE call_key = ?',
            [self::key($actor, $capabilityCall)],
        );
        $stored = [];

        foreach ($rows as $row) {
            if ($row->string('tool') !== $capabilityCall->tool) {
                $why = 'Invalid params: the call id names a call of another tool.';

                throw new McpProtocolException($why, McpProtocolException::INVALID_PARAMS);
            }

            $stored[] = self::replayed($row);
        }

        return $stored;
    }

    /**
     * @param Actor            $actor            Who called.
     * @param CapabilityCall   $capabilityCall   The call.
     * @param CapabilityResult $capabilityResult What it answered; a replayed answer is a row of its own, `replayed`.
     *
     * @throws QueryException When the row cannot be written.
     */
    public function record(Actor $actor, CapabilityCall $capabilityCall, CapabilityResult $capabilityResult): void
    {
        $done = $capabilityResult->callOutcome === CallOutcome::Done;

        $this->database->execute(
            'INSERT INTO aic_mcp_calls (call_key, call_id, client, user_id, tool, dry_run, outcome, arguments, output, created_at)'
            . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, now())',
            [
                self::kept($capabilityCall, $capabilityResult) ? self::key($actor, $capabilityCall) : null,
                $capabilityCall->callId,
                $actor->client,
                $actor->userId,
                $capabilityCall->tool,
                $capabilityCall->dryRun ? 'true' : 'false',
                $capabilityResult->replayed ? 'replayed' : $capabilityResult->callOutcome->value,
                $capabilityCall->arguments,
                $done ? $capabilityResult->output : $capabilityResult->refusal,
            ],
        );
    }

    /**
     * @param int $limit How many, 1 or more.
     *
     * @throws QueryException When the calls cannot be read.
     *
     * @return Rows The latest calls, newest first: when, client, user, tool, call id, dry, outcome.
     */
    public function latest(int $limit): Rows
    {
        return $this->database->fetchAll(
            "SELECT to_char(created_at AT TIME ZONE 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS\"Z\"') AS at, client, user_id, tool,"
            . ' call_id, dry_run, outcome FROM aic_mcp_calls ORDER BY id DESC LIMIT ?',
            [$limit],
        );
    }

    /**
     * @param CapabilityCall   $capabilityCall   The call.
     * @param CapabilityResult $capabilityResult What it answered.
     *
     * @return bool Whether its id is now taken: a first call with an id, done or refused.
     */
    private static function kept(CapabilityCall $capabilityCall, CapabilityResult $capabilityResult): bool
    {
        $outcome = $capabilityResult->callOutcome;

        return $capabilityCall->claims() && !$capabilityResult->replayed
            && ($outcome === CallOutcome::Done || $outcome === CallOutcome::Refused);
    }

    /**
     * @param Row $row A stored call: its outcome and output.
     *
     * @throws QueryException When a column is missing.
     *
     * @return CapabilityResult Its answer, replayed.
     */
    private static function replayed(Row $row): CapabilityResult
    {
        return $row->string('outcome') === CallOutcome::Done->value
            ? new CapabilityResult(CallOutcome::Done, $row->string('output'), '', false, true)
            : new CapabilityResult(CallOutcome::Refused, '', $row->string('output'), false, true);
    }

    /**
     * @param Actor          $actor          Who calls.
     * @param CapabilityCall $capabilityCall The call, with its id.
     *
     * @return string The SHA-256 of the caller and the id.
     */
    private static function key(Actor $actor, CapabilityCall $capabilityCall): string
    {
        return hash('sha256', (string) json_encode([$actor->client, $actor->userId, $capabilityCall->callId]));
    }
}
