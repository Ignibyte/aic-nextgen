<?php

declare(strict_types=1);

namespace App\Note\Form;

use AIC\Component\Domain\HiddenInput;
use AIC\Form\Application\EnvelopeCodec;
use AIC\Form\Exception\InvalidEnvelope;
use JsonException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Binds a note form to the note it was issued for (#43): the form carries a
 * signed envelope naming the note's path, and a post is accepted only when
 * the envelope is intact and names the note it posts to.
 */
final readonly class NoteEnvelope
{
    /**
     * @param EnvelopeCodec $envelopeCodec Seals and opens envelopes.
     */
    public function __construct(
        private EnvelopeCodec $envelopeCodec,
    ) {
    }

    /**
     * @param string $path The path the form posts to.
     *
     * @throws JsonException When the envelope cannot be encoded.
     *
     * @return HiddenInput The hidden envelope naming it.
     */
    public function field(string $path): HiddenInput
    {
        return $this->envelopeCodec->field(['note' => $path]);
    }

    /**
     * @param ServerRequestInterface $serverRequest A posted note form.
     * @param string                 $path          The note's path.
     *
     * @return bool Whether its envelope is intact and names this note.
     */
    public function matches(ServerRequestInterface $serverRequest, string $path): bool
    {
        try {
            $values = $this->envelopeCodec->open($serverRequest);
        } catch (InvalidEnvelope) {
            return false;
        }

        return ($values['note'] ?? '') === $path;
    }
}
