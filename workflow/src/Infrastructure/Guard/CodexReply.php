<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure\Guard;

use AIC\Workflow\Domain\Guard\GuardVerdict;
use JsonException;

use function json_encode;

use const JSON_INVALID_UTF8_SUBSTITUTE;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/** Emit only reply shapes the observed native client supports. */
final readonly class CodexReply
{
    /**
     * @param GuardVerdict $guardVerdict The independently rechecked MCP scope.
     *
     * @throws JsonException When reply encoding fails.
     *
     * @return string The supported native approval decision.
     */
    public function permission(GuardVerdict $guardVerdict): string
    {
        return json_encode(
            [
                'hookSpecificOutput' => [
                    'decision' => [
                        'behavior' => $guardVerdict->refused() ? 'deny' : 'allow',
                        'message' => $guardVerdict->message,
                    ],
                    'hookEventName' => 'PermissionRequest',
                ],
            ],
            JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES,
        );
    }

    /**
     * @param GuardVerdict $guardVerdict The existing wall's outcome.
     * @param string       $command      An owned replacement only after the original command passes.
     *
     * @throws JsonException When reply encoding fails.
     *
     * @return string The native tool decision.
     */
    public function pre(GuardVerdict $guardVerdict, string $command = ''): string
    {
        if ($guardVerdict->refused()) {
            return json_encode(
                ['hookSpecificOutput' => ['hookEventName' => 'PreToolUse', 'permissionDecision' => 'deny',
                'permissionDecisionReason' => $guardVerdict->message]],
                JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES,
            );
        }

        return $command === '' ? '{}' : json_encode(
            ['hookSpecificOutput' => ['hookEventName' => 'PreToolUse',
            'permissionDecision' => 'allow', 'updatedInput' => ['command' => $command]]],
            JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES,
        );
    }

    /**
     * @param GuardVerdict $guardVerdict The engine's Stop outcome.
     *
     * @throws JsonException When reply encoding fails.
     *
     * @return string The native Stop decision.
     */
    public function stop(GuardVerdict $guardVerdict): string
    {
        return $guardVerdict->refused()
            ? json_encode(['decision' => 'block', 'reason' => $guardVerdict->message], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE)
            : '{}';
    }

    /**
     * @param string $verb   The known callback verb.
     * @param string $reason The actual handler error.
     *
     * @throws JsonException When reply encoding fails.
     *
     * @return string A tool refusal or a lifecycle warning that cannot count as a review.
     */
    public function failure(string $verb, string $reason): string
    {
        $guardVerdict = new GuardVerdict('refuse', 'codex-input', $reason);

        return match ($verb) {
            'pre' => $this->pre($guardVerdict),
            'permission' => $this->permission($guardVerdict),
            default => json_encode(
                ['systemMessage' => 'AIC could not record this event: ' . $reason],
                JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE,
            ),
        };
    }
}
