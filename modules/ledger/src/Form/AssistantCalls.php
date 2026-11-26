<?php

declare(strict_types=1);

namespace App\Ledger\Form;

use AIC\Access\Application\Actors;
use AIC\Access\Domain\Actor;
use AIC\Database\Contracts\QueryException;
use AIC\Form\Application\PostedFields;
use AIC\Mcp\Application\Capabilities;
use AIC\Mcp\Contracts\McpArgumentException;
use AIC\Mcp\Domain\CapabilityCall;
use AIC\Mcp\Domain\CapabilityResult;
use AIC\Mcp\Domain\McpProtocolException;
use AIC\Mcp\Domain\McpToolInfo;
use App\Generated\LedgerMcp;
use Psr\Http\Message\ServerRequestInterface;

use function json_encode;

/**
 * The assistant's calls (#332): the signed-in person under the ceiling
 * `ledger.split`, named `ledger-assistant` for the audit, the tools that
 * leaves it, and the posted split through the capability registry.
 */
final readonly class AssistantCalls
{
    /**
     * The most the assistant may do, whatever the person holds.
     */
    public const array CEILING = ['ledger.split'];

    /**
     * The assistant's name, as the audit records it.
     */
    public const string CLIENT = 'ledger-assistant';

    /**
     * @param Capabilities $capabilities The registry.
     * @param LedgerMcp    $ledgerMcp    The ledger's catalog.
     * @param Actors       $actors       The person.
     * @param PostedFields $postedFields The posted fields.
     */
    public function __construct(
        private Capabilities $capabilities,
        private LedgerMcp $ledgerMcp,
        private Actors $actors,
        private PostedFields $postedFields = new PostedFields(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request.
     *
     * @throws QueryException When the person's roles cannot be read.
     *
     * @return Actor The assistant: the person, under the ceiling.
     */
    public function assistant(ServerRequestInterface $serverRequest): Actor
    {
        return $this->actors->actor($serverRequest)->within(self::CEILING, self::CLIENT);
    }

    /**
     * @param Actor $actor The assistant.
     *
     * @return list<McpToolInfo> The tools it may call.
     */
    public function tools(Actor $actor): array
    {
        return $this->capabilities->visible($this->ledgerMcp->catalog(), $actor);
    }

    /**
     * @param ServerRequestInterface $serverRequest The POST, its amount and parts whole numbers.
     * @param Actor                  $actor         The assistant.
     * @param string                 $callId        The call id the form carried.
     *
     * @throws QueryException       When the audit or the lock fails.
     * @throws McpProtocolException When the tool failed.
     * @throws McpArgumentException When the arguments did not fit.
     *
     * @return CapabilityResult What the call answered, dry when the box was ticked.
     */
    public function split(ServerRequestInterface $serverRequest, Actor $actor, string $callId): CapabilityResult
    {
        $arguments = (string) json_encode([
            'amount' => (int) $this->postedFields->value($serverRequest, 'amount'),
            'parts' => (int) $this->postedFields->value($serverRequest, 'parts'),
        ]);
        $capabilityCall = new CapabilityCall('ledger.split', $arguments, $callId, $this->postedFields->value($serverRequest, 'dry') !== '');

        return $this->capabilities->call($this->ledgerMcp->catalog(), $capabilityCall, $actor);
    }
}
