<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\Keyed;
use InvalidArgumentException;
use Override;

use function preg_match;
use function strlen;

/**
 * Any component under a key of the app's choosing (#70), so a region marks
 * it `<!--t:c KEY-->` and Tessera can refresh it as `REGION/KEY`.
 */
final readonly class KeyedComponent implements Keyed
{
    /**
     * @param string    $key       Letters, digits, `_`, `.`, `:` and single inner hyphens, up to 64 (protocol 2.4).
     * @param Component $component What it renders.
     * @param string    $props     Its inputs, signed (an EnvelopeCodec envelope), for Tessera to send back when it
     *                             asks for the component alone (protocol 2.5, #74); empty for none.
     *
     * @throws InvalidArgumentException When the key breaks the grammar, or the token is not 1 to 1,024 token characters.
     */
    public function __construct(
        private string $key,
        public Component $component,
        private string $props = '',
    ) {
        if ($props !== '' && (strlen($props) > 1_024 || preg_match('#^[A-Za-z0-9\\-_.~+/=]+$#', $props) !== 1)) {
            throw new InvalidArgumentException('A props token is 1 to 1,024 of A-Z a-z 0-9 - _ . ~ + / =.');
        }

        if (strlen($key) > 64 || preg_match('/^[A-Za-z0-9_.:]+(-[A-Za-z0-9_.:]+)*$/', $key) !== 1) {
            throw new InvalidArgumentException(
                'A component key is letters, digits, _, ., : and inner hyphens, up to 64; got "' . $key . '".',
            );
        }
    }

    /**
     * @return string Its key.
     */
    #[Override]
    public function key(): string
    {
        return $this->key;
    }

    /**
     * @return string Its signed props token; empty for none.
     */
    #[Override]
    public function props(): string
    {
        return $this->props;
    }
}
