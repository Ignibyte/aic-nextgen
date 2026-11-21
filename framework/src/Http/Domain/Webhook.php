<?php

declare(strict_types=1);

namespace AIC\Http\Domain;

use InvalidArgumentException;

use function hash_equals;
use function hash_hmac;
use function str_starts_with;
use function strtolower;
use function substr;
use function trim;

/**
 * One webhook an app takes (#321), from `config/app.php`: the header its
 * sender signs a delivery in, the shared secret and the scheme. A delivery
 * whose signature matches is judged by it alone: it skips the CSRF check.
 */
final readonly class Webhook
{
    /**
     * The request attribute a verified delivery carries: its path.
     */
    public const string ATTRIBUTE = 'aic.webhook';

    /**
     * The prefix some senders put before the hex digest.
     */
    private const string PREFIX = 'sha256=';

    /**
     * @param string        $header        The header that carries the signature, such as `X-Signature`.
     * @param string        $secret        The shared secret, from the environment; blank refuses every delivery.
     * @param WebhookScheme $webhookScheme How the sender signs.
     *
     * @throws InvalidArgumentException When the header is blank.
     */
    public function __construct(
        public string $header,
        private string $secret,
        public WebhookScheme $webhookScheme = WebhookScheme::HmacSha256,
    ) {
        if (trim($header) === '') {
            throw new InvalidArgumentException('Webhook: the signature header must not be blank.');
        }
    }

    /**
     * @param string $body      The delivery's raw body.
     * @param string $signature Its signature header's value.
     *
     * @return bool Whether the signature is the body's HMAC under a secret that is not blank, by the scheme.
     */
    public function signed(string $body, string $signature): bool
    {
        if ($this->secret === '') {
            return false;
        }

        $digest = str_starts_with($signature, self::PREFIX)
            ? substr($signature, 7)
            : $signature;

        return hash_equals(hash_hmac($this->webhookScheme->algorithm(), $body, $this->secret), strtolower($digest));
    }
}
