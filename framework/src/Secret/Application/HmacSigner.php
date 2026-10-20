<?php

declare(strict_types=1);

namespace AIC\Secret\Application;

use AIC\Secret\Domain\AppSecret;

use function hash_equals;
use function hash_hmac;

/**
 * Signs messages with the app secret: HMAC-SHA256 as hex, checked in
 * constant time.
 */
final readonly class HmacSigner
{
    /**
     * @param AppSecret $appSecret The key.
     */
    public function __construct(
        private AppSecret $appSecret,
    ) {
    }

    /**
     * @param string $message A message.
     *
     * @return string Its signature, 64 hex characters.
     */
    public function sign(string $message): string
    {
        return hash_hmac('sha256', $message, $this->appSecret->reveal());
    }

    /**
     * @param string $message   A message.
     * @param string $signature The signature it came with.
     *
     * @return bool Whether the signature is the message's.
     */
    public function verify(string $message, string $signature): bool
    {
        return hash_equals($this->sign($message), $signature);
    }
}
