<?php

declare(strict_types=1);

namespace AIC\Mcp\Domain;

use AIC\Mcp\Contracts\McpArguments;

use function preg_match;

/**
 * One call of a capability (#332): the tool's name, its arguments as a JSON
 * object's text, the caller's call id (empty for none), and whether to run it
 * dry. An MCP client gives the id and the flag in `params._meta` as
 * `aic/callId` and `aic/dryRun`; an app's assistant builds one itself.
 */
final readonly class CapabilityCall
{
    /**
     * A call id: 1 to 64 letters, digits, `.`, `_`, `:` or `-`.
     */
    private const string CALL_ID = '/^[A-Za-z0-9._:-]{1,64}$/';

    /**
     * @param string $tool      The tool's name.
     * @param string $arguments Its arguments, a JSON object.
     * @param string $callId    The caller's id for the call, applied once; empty for none.
     * @param bool   $dryRun    Whether to run it dry, its writes rolled back.
     *
     * @throws McpProtocolException When the call id is not one (-32602).
     */
    public function __construct(
        public string $tool,
        public string $arguments = '{}',
        public string $callId = '',
        public bool $dryRun = false,
    ) {
        if ($callId !== '' && preg_match(self::CALL_ID, $callId) !== 1) {
            throw new McpProtocolException(
                'Invalid params: a call id is 1 to 64 letters, digits, ".", "_", ":" or "-".',
                McpProtocolException::INVALID_PARAMS,
            );
        }
    }

    /**
     * @return McpArguments Its arguments, to decode.
     */
    public function input(): McpArguments
    {
        return new McpArguments($this->arguments);
    }

    /**
     * @return bool Whether it is applied once: it has a call id and is not a dry run.
     */
    public function claims(): bool
    {
        return $this->callId !== '' && !$this->dryRun;
    }
}
