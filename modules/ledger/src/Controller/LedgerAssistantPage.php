<?php

declare(strict_types=1);

namespace App\Ledger\Controller;

use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use AIC\Mcp\Contracts\McpArgumentException;
use AIC\Mcp\Domain\McpProtocolException;
use App\Ledger\Service\LedgerAssistant;
use JsonException;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET and POST /ledger/assistant (#332): the fixture's in-app assistant,
 * which calls the ledger's tools through the capability registry for the
 * signed-in person under a permission ceiling. `LedgerAssistant` answers.
 */
final readonly class LedgerAssistantPage implements Page
{
    /**
     * @param LedgerAssistant $ledgerAssistant The assistant.
     */
    public function __construct(
        private LedgerAssistant $ledgerAssistant,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The GET or the POST.
     *
     * @throws LogicException       When the request carries no session, or the page cannot be built.
     * @throws QueryException       When the person, the audit or the lock cannot be read or written.
     * @throws JsonException        When the call id cannot be sealed.
     * @throws McpProtocolException When the tool failed.
     * @throws McpArgumentException When the arguments did not fit.
     *
     * @return PageResponse The page.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        return $this->ledgerAssistant->answer($serverRequest);
    }
}
