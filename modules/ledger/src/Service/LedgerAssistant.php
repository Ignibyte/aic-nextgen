<?php

declare(strict_types=1);

namespace App\Ledger\Service;

use AIC\Component\Contracts\Component;
use AIC\Controller\Application\PageDocuments;
use AIC\Controller\Contracts\PageResponse;
use AIC\Database\Contracts\QueryException;
use AIC\Form\Application\PostedFields;
use AIC\Mcp\Contracts\McpArgumentException;
use AIC\Mcp\Domain\McpProtocolException;
use App\Ledger\Form\AssistantCalls;
use App\Ledger\Form\AssistantEnvelope;
use App\Ledger\Form\AssistantForm;
use App\Ledger\Form\AssistantResult;
use InvalidArgumentException;
use JsonException;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

use function ctype_digit;

/**
 * The fixture's in-app assistant (#332): what an assistant acting for the
 * signed-in person may call, through the same capability registry as the MCP
 * endpoint, under the ceiling `ledger.split`. A GET shows the tools and a form
 * whose call id is sealed in its envelope; a POST splits the amount, dry when
 * asked, and a resubmitted id answers the first call's result.
 */
final readonly class LedgerAssistant
{
    /**
     * @param AssistantCalls    $assistantCalls    The assistant and its calls.
     * @param AssistantEnvelope $assistantEnvelope The form's sealed call id.
     * @param PageDocuments     $pageDocuments     The page.
     * @param AssistantForm     $assistantForm     The page's parts.
     * @param AssistantResult   $assistantResult   A post's answer.
     * @param PostedFields      $postedFields      The posted fields.
     */
    public function __construct(
        private AssistantCalls $assistantCalls,
        private AssistantEnvelope $assistantEnvelope,
        private PageDocuments $pageDocuments,
        private AssistantForm $assistantForm = new AssistantForm(),
        private AssistantResult $assistantResult = new AssistantResult(),
        private PostedFields $postedFields = new PostedFields(),
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
     * @return PageResponse The page: the tools, the form and, after a POST, the result.
     */
    public function answer(ServerRequestInterface $serverRequest): PageResponse
    {
        $actor = $this->assistantCalls->assistant($serverRequest);
        $tools = $this->assistantCalls->tools($actor);
        $fresh = $this->assistantEnvelope->fresh();

        if ($serverRequest->getMethod() !== 'POST') {
            return $this->page($this->assistantForm->page($serverRequest, $tools, $fresh, '100', '3', []));
        }

        $amount = $this->postedFields->value($serverRequest, 'amount');
        $parts = $this->postedFields->value($serverRequest, 'parts');
        $callId = $this->assistantEnvelope->callId($serverRequest);

        if (!ctype_digit($amount) || !ctype_digit($parts) || $callId === '') {
            $unfit = $this->assistantResult->unfit();

            return $this->page($this->assistantForm->page($serverRequest, $tools, $fresh, $amount, $parts, $unfit), 422);
        }

        $capabilityResult = $this->assistantCalls->split($serverRequest, $actor, $callId);
        $same = $this->assistantEnvelope->sealed($callId);
        $result = $this->assistantResult->shown($serverRequest, $capabilityResult, $same, $amount, $parts);
        $next = $capabilityResult->dryRun
            ? $same
            : $fresh;

        return $this->page($this->assistantForm->page($serverRequest, $tools, $next, $amount, $parts, $result));
    }

    /**
     * @param list<Component> $components The page's parts.
     * @param int             $status     The status.
     *
     * @throws InvalidArgumentException When the page cannot be built.
     *
     * @return PageResponse The page.
     */
    private function page(array $components, int $status = 200): PageResponse
    {
        return $this->pageDocuments->content($components, $status, 'Ledger assistant');
    }
}
