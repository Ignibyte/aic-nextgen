<?php

declare(strict_types=1);

namespace AIC\Mcp\Domain;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use Override;

/**
 * The capability registry's audit (#332): `aic_mcp_calls`, one row for every
 * call of a tool, by an MCP client or an app's own assistant: who called (the
 * client and the person), the tool, the call id, whether it ran dry, how it
 * ended, its arguments and its output (or its refusal). `call_key` is the
 * SHA-256 of the caller and the call id, set on a call that took one and
 * ended done or refused, so a call id is applied once per caller; a repeat
 * of that id is a row of its own, its outcome `replayed`.
 */
final readonly class CreateMcpCallsTable implements Migration
{
    /**
     * @return string The version.
     */
    #[Override]
    public function version(): string
    {
        return '2026_11_26_000002_create_aic_mcp_calls';
    }

    /**
     * @param Schema $schema The schema.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $schema->create('aic_mcp_calls')
            ->identity('id')
            ->string('call_key', 64)
            ->nullable()
            ->text('call_id')
            ->text('client')
            ->text('user_id')
            ->text('tool')
            ->boolean('dry_run')
            ->string('outcome', 16)
            ->text('arguments')
            ->text('output')
            ->timestampTz('created_at')
            ->primary(['id'])
            ->unique(['call_key']);
    }
}
