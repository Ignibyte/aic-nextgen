<?php

declare(strict_types=1);

namespace AIC\Http\Application;

use AIC\Http\Domain\Webhook;
use AIC\Http\Domain\Webhooks;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Checks a webhook delivery's signature (#321): a POST to a path
 * `config/app.php`'s webhooks name answers 401 unless its signature header
 * is the body's HMAC-SHA256 under that webhook's secret. A delivery that
 * matches goes on carrying `Webhook::ATTRIBUTE`, which spares it the form
 * guard's CSRF check. Every other request passes untouched, so a webhook
 * route the config does not name keeps the CSRF check and is refused.
 */
final readonly class WebhookSignatureMiddleware implements MiddlewareInterface
{
    /**
     * @param Webhooks        $webhooks        The webhooks, by path.
     * @param ResponseBuilder $responseBuilder Makes the refusal.
     */
    public function __construct(
        private Webhooks $webhooks,
        private ResponseBuilder $responseBuilder,
    ) {
    }

    /**
     * @param ServerRequestInterface  $request The request.
     * @param RequestHandlerInterface $handler The rest of the stack.
     *
     * @throws InvalidArgumentException When the refusal's status is out of range.
     *
     * @return ResponseInterface The refusal, or the stack's answer.
     */
    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        if ($request->getMethod() !== 'POST' || !$this->webhooks->has($path)) {
            return $handler->handle($request);
        }

        $webhook = $this->webhooks->webhook($path);

        if (!$webhook->signed((string) $request->getBody(), $request->getHeaderLine($webhook->header))) {
            return $this->responseBuilder->create(401, 'text/plain; charset=utf-8', "The webhook signature does not match.\n");
        }

        return $handler->handle($request->withAttribute(Webhook::ATTRIBUTE, $path));
    }
}
