<?php

declare(strict_types=1);

namespace App\Ledger\Form;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\Button;
use AIC\Component\Domain\Checkbox;
use AIC\Component\Domain\Form;
use AIC\Component\Domain\FormField;
use AIC\Component\Domain\Heading;
use AIC\Component\Domain\HiddenInput;
use AIC\Component\Domain\Input;
use AIC\Component\Domain\Paragraph;
use AIC\Form\Application\EnvelopeCodec;
use AIC\Form\Application\FormTokens;
use AIC\Mcp\Domain\McpToolInfo;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The assistant page (#332): the tools the assistant may call, what the last
 * post answered (`AssistantResult`), and the split form, its call id sealed in
 * the envelope (`AssistantEnvelope`).
 */
final readonly class AssistantForm
{
    /**
     * Where the form is posted.
     */
    public const string ACTION = '/ledger/assistant';

    /**
     * @param FormTokens     $formTokens     Issues the visitor's CSRF token.
     * @param AssistantTools $assistantTools The tools' table.
     */
    public function __construct(
        private FormTokens $formTokens = new FormTokens(),
        private AssistantTools $assistantTools = new AssistantTools(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, with its session.
     * @param list<McpToolInfo>      $tools         The tools the assistant may call.
     * @param string                 $envelope      The form's sealed call id.
     * @param string                 $amount        The amount to show.
     * @param string                 $parts         The parts to show.
     * @param list<Component>        $result        What the last post answered; none for a GET.
     *
     * @throws LogicException When the request carries no session.
     *
     * @return list<Component> The page's parts.
     */
    public function page(
        ServerRequestInterface $serverRequest,
        array $tools,
        string $envelope,
        string $amount,
        string $parts,
        array $result,
    ): array {
        return [
            new Heading('Ledger assistant'),
            new Paragraph('Your assistant may call what you hold, within ledger.split.', 'assistant-ceiling'),
            $this->assistantTools->table($tools),
            ...$result,
            new Form('assistant-form', self::ACTION, [
                new FormField('Amount in cents', 'assistant-amount', new Input('assistant-amount', 'amount', $amount)),
                new FormField('Parts', 'assistant-parts', new Input('assistant-parts', 'parts', $parts)),
                new FormField('Preview only (a dry run)', 'assistant-dry', new Checkbox('assistant-dry', 'dry', false)),
                new HiddenInput(EnvelopeCodec::FIELD, $envelope),
                new Button('Split', 'submit', 'assistant-split'),
            ], $this->formTokens->guard($serverRequest)),
        ];
    }
}
