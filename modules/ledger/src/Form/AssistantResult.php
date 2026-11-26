<?php

declare(strict_types=1);

namespace App\Ledger\Form;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\Alert;
use AIC\Component\Domain\AlertSeverity;
use AIC\Component\Domain\Button;
use AIC\Component\Domain\CodeBlock;
use AIC\Component\Domain\Form;
use AIC\Component\Domain\HiddenInput;
use AIC\Component\Domain\Paragraph;
use AIC\Form\Application\EnvelopeCodec;
use AIC\Form\Application\FormTokens;
use AIC\Mcp\Domain\CallOutcome;
use AIC\Mcp\Domain\CapabilityResult;
use LogicException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * What a post of the assistant's form answered (#332): the tool's output,
 * marked as a dry run, a split or a replay, or its refusal; after a kept
 * call, a form that sends the same call again.
 */
final readonly class AssistantResult
{
    /**
     * @param FormTokens $formTokens Issues the visitor's CSRF token.
     */
    public function __construct(
        private FormTokens $formTokens = new FormTokens(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest    The request, with its session.
     * @param CapabilityResult       $capabilityResult What the call answered.
     * @param string                 $envelope         The answered call's sealed id.
     * @param string                 $amount           Its amount.
     * @param string                 $parts            Its parts.
     *
     * @throws LogicException When the request carries no session.
     *
     * @return list<Component> The result and, after a kept call, the form that sends it again.
     */
    public function shown(
        ServerRequestInterface $serverRequest,
        CapabilityResult $capabilityResult,
        string $envelope,
        string $amount,
        string $parts,
    ): array {
        if ($capabilityResult->callOutcome !== CallOutcome::Done) {
            return [new Alert(AlertSeverity::Error, $capabilityResult->refusal, 'assistant-refused')];
        }

        $shown = [new Paragraph(self::said($capabilityResult), 'assistant-result'), new CodeBlock($capabilityResult->output)];

        return $capabilityResult->dryRun
            ? $shown
            : [...$shown, new Form('assistant-again-form', AssistantForm::ACTION, [
                new HiddenInput('amount', $amount),
                new HiddenInput('parts', $parts),
                new HiddenInput(EnvelopeCodec::FIELD, $envelope),
                new Button('Send the same call again', 'submit', 'assistant-again'),
            ], $this->formTokens->guard($serverRequest))];
    }

    /**
     * @return list<Component> Why a post was not sent.
     */
    public function unfit(): array
    {
        return [new Alert(AlertSeverity::Error, 'Give the amount and the parts as whole numbers.', 'assistant-refused')];
    }

    /**
     * @param CapabilityResult $capabilityResult A done call.
     *
     * @return string What it was: a dry run, a replay or a split.
     */
    private static function said(CapabilityResult $capabilityResult): string
    {
        if ($capabilityResult->dryRun) {
            return 'A dry run: nothing was kept.';
        }

        return $capabilityResult->replayed
            ? 'Replayed: this call was already made, so its first answer is shown and nothing ran again.'
            : 'Split. The call is kept under its id.';
    }
}
