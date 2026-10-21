<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\Html;
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
     *
     * @throws InvalidArgumentException When the key breaks the grammar.
     */
    public function __construct(
        private string $key,
        private Component $component,
    ) {
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
     * @return Html What the component renders.
     */
    #[Override]
    public function render(): Html
    {
        return $this->component->render();
    }
}
