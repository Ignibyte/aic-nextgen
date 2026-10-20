<?php

declare(strict_types=1);

namespace AIC\Session\Domain;

use AIC\Session\Contracts\Session;
use Override;

use function array_key_exists;

/**
 * A session as the middleware loaded it: its id and its values, changed in
 * place during the request and saved at its end.
 */
final class StoredSession implements Session
{
    /**
     * @param string                $id     Its id.
     * @param array<string, string> $values Its values by key.
     */
    public function __construct(
        private readonly string $id,
        private array $values,
    ) {
    }

    /**
     * @return string Its id.
     */
    public function sessionId(): string
    {
        return $this->id;
    }

    /**
     * @param string $key A key.
     *
     * @return string|null Its value; null when unset.
     */
    #[Override]
    public function get(string $key): ?string
    {
        return array_key_exists($key, $this->values)
            ? $this->values[$key]
            : null;
    }

    /**
     * @param string $key   A key.
     * @param string $value Its new value.
     */
    #[Override]
    public function set(string $key, string $value): void
    {
        $this->values[$key] = $value;
    }

    /**
     * @return array<string, string> Every value, by key.
     */
    public function all(): array
    {
        return $this->values;
    }

    /**
     * Unsets every value.
     */
    #[Override]
    public function clear(): void
    {
        $this->values = [];
    }
}
