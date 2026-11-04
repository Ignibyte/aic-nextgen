<?php

declare(strict_types=1);

namespace AIC\Profiler\Application;

use AIC\Controller\Contracts\ResponseWriter;
use AIC\Database\Contracts\QueryException;
use AIC\Http\Application\ResponseBuilder;
use AIC\Profiler\Domain\ProfilerStyles;
use AIC\Tessera\Application\TesseraRequests;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function str_starts_with;

/**
 * The profiler's pages (#230), answered before the app like its assets and
 * stored files, so no route, session or token is involved: `/_profiler` lists
 * the kept requests, `/_profiler/{id}` shows one, `/_profiler/styles.css` is
 * their stylesheet and `/_profiler/stream` the toolbar's live list (#231).
 * They answer Tessera's fragment requests the way an app's
 * pages do. Only GET and HEAD; anything else under the prefix is 405.
 * `ProfilerMount` puts it in front of the app only while profiling.
 */
final readonly class ProfilerPages implements RequestHandlerInterface
{
    /**
     * @param RequestHandlerInterface $requestHandler    What answers everything else.
     * @param ProfilerDocuments       $profilerDocuments Builds each page.
     * @param ProfilerFeed            $profilerFeed      The toolbar's stream (#231).
     * @param ResponseWriter          $responseWriter    Writes a page, whole or as Tessera fragments.
     * @param ResponseBuilder         $responseBuilder   Makes the stylesheet's and the refusals' responses.
     * @param TesseraRequests         $tesseraRequests   Reads the request's Tessera headers (#268).
     */
    public function __construct(
        private RequestHandlerInterface $requestHandler,
        private ProfilerDocuments $profilerDocuments,
        private ProfilerFeed $profilerFeed,
        private ResponseWriter $responseWriter,
        private ResponseBuilder $responseBuilder,
        private TesseraRequests $tesseraRequests = new TesseraRequests(),
    ) {
    }

    /**
     * @param ServerRequestInterface $request A request.
     *
     * @throws QueryException           When the profiles cannot be read.
     * @throws InvalidArgumentException When a component, key or status is invalid.
     *
     * @return ResponseInterface A profiler page or the stylesheet; anything else, as the app answers it.
     */
    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        if ($path !== ProfilerDocuments::PATH && !str_starts_with($path, ProfilerDocuments::PATH . '/')) {
            return $this->requestHandler->handle($request);
        }

        if ($request->getMethod() !== 'GET' && $request->getMethod() !== 'HEAD') {
            return $this->responseBuilder->create(405, 'text/plain; charset=utf-8', 'Method Not Allowed');
        }

        return $this->answer($request, $path);
    }

    /**
     * @param ServerRequestInterface $serverRequest A GET or HEAD under the prefix.
     * @param string                 $path          Its path.
     *
     * @throws QueryException           When the profiles cannot be read.
     * @throws InvalidArgumentException When a component, key or status is invalid.
     *
     * @return ResponseInterface The stylesheet, the toolbar's stream, or a page, whole or as Tessera fragments.
     */
    private function answer(ServerRequestInterface $serverRequest, string $path): ResponseInterface
    {
        if ($path === ProfilerStyles::PATH) {
            return $this->responseBuilder->create(200, 'text/css; charset=utf-8', ProfilerStyles::CSS);
        }

        if ($path === ProfilerFeed::PATH) {
            return $this->profilerFeed->stream($serverRequest)->writeTo($this->responseWriter);
        }

        $tesseraRequest = $this->tesseraRequests->read($serverRequest);

        return $this->profilerDocuments->document($serverRequest, $path)->answer($tesseraRequest)->writeTo($this->responseWriter);
    }
}
