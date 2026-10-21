<?php

declare(strict_types=1);

namespace AIC\Tessera\Application;

use AIC\Database\Contracts\QueryException;
use AIC\Form\Contracts\FormToken;
use AIC\Http\Application\ResponseBuilder;
use AIC\Session\Application\RequestSession;
use AIC\Tessera\Domain\RecordedWrite;
use AIC\Tessera\Domain\TesseraRequest;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function explode;
use function hash;
use function implode;
use function in_array;
use function preg_match;

/**
 * Applies a write once per `Tessera-Write-Id` (#72, protocol 5.8). A browser
 * may repeat a request by itself, with the same id; the repeat gets the first
 * answer again, or `409` while the first is unsettled, and a malformed id gets
 * `400`. Ids are scoped by a hash of the session's CSRF token, so it sits
 * inside the guard. A write without the header runs as before.
 */
final readonly class TesseraWriteMiddleware implements MiddlewareInterface
{
    /**
     * The answer's headers that are recorded and sent again; the session's cookie comes from outside.
     */
    private const array HEADERS = [
        'Content-Type',
        'Location',
        'Tessera-Location',
        'Tessera-Full-Visit',
        'Tessera-Protocol',
        'Vary',
        'Cache-Control',
    ];

    /**
     * @param TesseraWrites   $tesseraWrites   The ids seen and their answers.
     * @param RequestSession  $requestSession  Reads the session off the request.
     * @param ResponseBuilder $responseBuilder Rebuilds a recorded answer.
     */
    public function __construct(
        private TesseraWrites $tesseraWrites,
        private RequestSession $requestSession,
        private ResponseBuilder $responseBuilder,
    ) {
    }

    /**
     * @param ServerRequestInterface  $request The request, its CSRF token checked.
     * @param RequestHandlerInterface $handler The rest of the stack.
     *
     * @throws QueryException When the ids cannot be read or written.
     *
     * @return ResponseInterface The write's answer, the first answer again, 409 or 400.
     */
    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $writeId = $request->getHeaderLine(TesseraRequest::WRITE_ID);

        if ($writeId === '' || in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return $handler->handle($request);
        }

        if (preg_match('/^[A-Za-z0-9-]{1,64}$/', $writeId) !== 1) {
            return $this->plain(400, 'A Tessera-Write-Id is 1 to 64 letters, digits and hyphens.');
        }

        $scope = hash('sha256', $this->requestSession->read($request)->get(FormToken::SESSION_KEY) ?? '');

        if (!$this->tesseraWrites->claim($scope, $writeId)) {
            return $this->repeated($this->tesseraWrites->answer($scope, $writeId));
        }

        $response = $handler->handle($request);
        $this->tesseraWrites->record($scope, $writeId, $this->recorded($response));

        return $response;
    }

    /**
     * @param ResponseInterface $response A write's answer.
     *
     * @return RecordedWrite It as it is kept.
     */
    private function recorded(ResponseInterface $response): RecordedWrite
    {
        $values = [];

        foreach (self::HEADERS as $name) {
            $values[] = $response->getHeaderLine($name);
        }

        // Read as a stream, not cast: elephc does not call __toString() through StreamInterface (#78).
        $stream = $response->getBody();

        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        return new RecordedWrite($response->getStatusCode(), implode("\n", $values), $stream->getContents());
    }

    /**
     * @param RecordedWrite $recordedWrite The first write's answer.
     *
     * @return ResponseInterface It again; 409 when the first never settled.
     */
    private function repeated(RecordedWrite $recordedWrite): ResponseInterface
    {
        if (!$recordedWrite->settled()) {
            return $this->plain(409, 'This write is already being applied.');
        }

        $values = explode("\n", $recordedWrite->headers);
        $response = $this->responseBuilder->create($recordedWrite->status, $values[0], $recordedWrite->body);

        foreach (self::HEADERS as $position => $name) {
            $response = $this->withValue($response, $name, $position === 0 ? '' : $values[$position] ?? '');
        }

        return $response;
    }

    /**
     * @param ResponseInterface $response The answer being rebuilt.
     * @param string            $name     A recorded header.
     * @param string            $value    Its value; empty when it had none.
     *
     * @return ResponseInterface The answer with it.
     */
    private function withValue(ResponseInterface $response, string $name, string $value): ResponseInterface
    {
        return $value === ''
            ? $response
            : $this->responseBuilder->withHeader($response, $name, $value);
    }

    /**
     * @param int    $status  400 or 409.
     * @param string $message Why.
     *
     * @return ResponseInterface A plain answer Tessera reads as a failed write.
     */
    private function plain(int $status, string $message): ResponseInterface
    {
        $response = $this->responseBuilder->create($status, 'text/plain; charset=utf-8', $message . "\n");
        $response = $this->responseBuilder->withHeader($response, TesseraRequest::PROTOCOL, TesseraRequest::VERSION);

        return $this->responseBuilder->withHeader($response, 'Vary', TesseraRequest::VARY);
    }
}
