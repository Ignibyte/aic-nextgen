<?php

declare(strict_types=1);

namespace AIC\Secret\Domain;

use InvalidArgumentException;

use function strlen;

/**
 * The app's secret, the key every signature uses (#43). It comes from the
 * environment (`AIC_APP_SECRET`, config/secret.php) and never from a file in
 * the repository; the app does not boot without one of 32 bytes or more.
 */
final readonly class AppSecret
{
    /**
     * The fewest bytes a secret may have.
     */
    private const int MIN_LENGTH = 32;

    /**
     * @param string $secret The secret.
     *
     * @throws InvalidArgumentException When it is shorter than 32 bytes: unset, or too weak to sign with.
     */
    public function __construct(
        private string $secret,
    ) {
        if (strlen($secret) < self::MIN_LENGTH) {
            throw new InvalidArgumentException(
                'AIC_APP_SECRET must hold at least ' . self::MIN_LENGTH . ' bytes; it holds ' . strlen($secret) . '.',
            );
        }
    }

    /**
     * @return string The secret, for a signer.
     */
    public function reveal(): string
    {
        return $this->secret;
    }
}
