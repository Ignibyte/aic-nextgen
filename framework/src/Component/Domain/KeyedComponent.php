<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\Keyed;
use InvalidArgumentException;
use Override;

use function preg_match;
use function sprintf;
use function strlen;

/**
 * Any component under a key of the app's choosing (#70), so a region marks
 * it `data-t-c="KEY"` on its root element and Tessera can refresh it as `REGION/KEY`.
 */
final readonly class KeyedComponent implements Keyed
{
    /**
     * What a key that breaks the grammar is told.
     */
    private const string KEY = 'A component key is letters, digits, _, ., : and inner hyphens, up to 64; got "%s".';

    /**
     * @param string    $key       Letters, digits, `_`, `.`, `:` and single inner hyphens, up to 64 (protocol 2.4).
     * @param Component $component What it renders.
     * @param string    $props     Its inputs, signed (an EnvelopeCodec envelope), for Tessera to send back when it
     *                             asks for the component alone (protocol 2.5, #74); empty for none.
     * @param string    $anchor    A name a link or form targets as `@NAME`, in the key grammar and unique on a page
     *                             (#188); empty for none.
     *
     * @throws InvalidArgumentException When the key or the anchor breaks the grammar, or the token is not 1 to 1,024
     *                                  token characters.
     */
    public function __construct(
        private string $key,
        public Component $component,
        private string $props = '',
        private string $anchor = '',
    ) {
        $problem = self::problem($key, $props, $anchor);

        if ($problem !== '') {
            throw new InvalidArgumentException($problem);
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

    /**
     * @return string Its anchor; empty for none.
     */
    #[Override]
    public function anchor(): string
    {
        return $this->anchor;
    }

    /**
     * @param string $key    A key.
     * @param string $props  A props token, or empty.
     * @param string $anchor An anchor, or empty.
     *
     * @return string What breaks the grammar; empty when nothing does.
     */
    private static function problem(string $key, string $props, string $anchor): string
    {
        return match (true) {
            !self::isKey($key) => sprintf(self::KEY, $key),
            $props !== '' && !self::isToken($props) => 'A props token is 1 to 1,024 of A-Z a-z 0-9 - _ . ~ + / =.',
            $anchor !== '' && !self::isKey($anchor) => 'An anchor is in the key grammar; got "' . $anchor . '".',
            default => '',
        };
    }

    /**
     * @param string $props A props token.
     *
     * @return bool Whether it is 1 to 1,024 token characters (protocol 2.5).
     */
    private static function isToken(string $props): bool
    {
        return strlen($props) <= 1_024 && preg_match('#^[A-Za-z0-9\\-_.~+/=]+$#', $props) === 1;
    }

    /**
     * @param string $name A key or an anchor.
     *
     * @return bool Whether it is letters, digits, `_`, `.`, `:` and single inner hyphens, up to 64 (protocol 2.4).
     */
    private static function isKey(string $name): bool
    {
        return strlen($name) <= 64 && preg_match('/^[A-Za-z0-9_.:]+(-[A-Za-z0-9_.:]+)*$/', $name) === 1;
    }
}
