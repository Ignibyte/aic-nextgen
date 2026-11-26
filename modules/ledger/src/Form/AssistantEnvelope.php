<?php

declare(strict_types=1);

namespace App\Ledger\Form;

use AIC\Form\Application\EnvelopeCodec;
use AIC\Form\Exception\InvalidEnvelope;
use AIC\Session\Domain\RandomTokens;
use JsonException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The assistant form's call id (#332), sealed in its envelope so a visitor
 * can resend it but never forge one: a fresh id for each new call, the same
 * id for a resend, and the id a post carries.
 */
final readonly class AssistantEnvelope
{
    /**
     * @param EnvelopeCodec $envelopeCodec Seals and opens it.
     * @param RandomTokens  $randomTokens  A fresh call id.
     */
    public function __construct(
        private EnvelopeCodec $envelopeCodec,
        private RandomTokens $randomTokens = new RandomTokens(),
    ) {
    }

    /**
     * @throws JsonException When it cannot be sealed.
     *
     * @return string A fresh call id's envelope.
     */
    public function fresh(): string
    {
        return $this->sealed($this->randomTokens->fresh());
    }

    /**
     * @param string $callId A call id.
     *
     * @throws JsonException When it cannot be sealed.
     *
     * @return string Its envelope.
     */
    public function sealed(string $callId): string
    {
        return $this->envelopeCodec->seal(['call' => $callId]);
    }

    /**
     * @param ServerRequestInterface $serverRequest The POST.
     *
     * @return string The call id its envelope carries; empty when it carries none or was altered.
     */
    public function callId(ServerRequestInterface $serverRequest): string
    {
        try {
            return $this->envelopeCodec->open($serverRequest)['call'] ?? '';
        } catch (InvalidEnvelope) {
            return '';
        }
    }
}
