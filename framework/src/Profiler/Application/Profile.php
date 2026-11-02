<?php

declare(strict_types=1);

namespace AIC\Profiler\Application;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\RenderObserver;
use AIC\Http\Application\ResponseBuilder;
use AIC\Kernel\Contracts\RequestObserver;
use AIC\Kernel\Domain\Monotonic;
use AIC\Profiler\Domain\ComponentRecord;
use AIC\Profiler\Domain\ProfileTimes;
use AIC\Profiler\Domain\RequestFacts;
use AIC\Profiler\Domain\StatementRecord;
use AIC\Routing\Contracts\RouteObserver;
use AIC\Tessera\Domain\TesseraRequest;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function str_starts_with;

/**
 * One request's profile while it runs (#229): told by the dynamic
 * application, the router, the Renderer and the profiled database, and kept by
 * the writer when the request answers, named in `X-AIC-Profile`. It lives for
 * one request, as each request starts with fresh PHP state. The profiler's
 * own pages (`/_profiler`) are not recorded.
 */
final class Profile implements RenderObserver, RequestObserver, RouteObserver
{
    /** The response header that names the profile. */
    public const string HEADER = 'X-AIC-Profile';

    /** The Tessera request headers a profile keeps; no other header is read. */
    private const array TESSERA = [
        TesseraRequest::REQUEST,
        TesseraRequest::PROTOCOL,
        TesseraRequest::TARGETS,
        TesseraRequest::LAYOUT,
        TesseraRequest::WRITE_ID,
    ];

    /** The answer headers a profile keeps. */
    private const array ANSWER = ['Content-Type', 'Tessera-Protocol', 'Tessera-Location', TesseraRequest::FULL_VISIT];

    private bool $recording = false;

    private int $started = 0;

    private int $routed = 0;

    private int $answered = 0;

    private int $render = 0;

    private string $handler = '';

    private string $user = '';

    private string $kind = '';

    private string $browser = '';

    private string $id = '';

    /** @var list<StatementRecord> */
    private array $statements = [];

    /** @var list<ComponentRecord> */
    private array $components = [];

    /**
     * @param ProfileWriter    $profileWriter    Keeps the profile.
     * @param ResponseBuilder  $responseBuilder  Adds the header naming it.
     * @param Monotonic        $monotonic        The clock the times are read from.
     * @param ExchangeReader   $exchangeReader   Reads the request and its answer.
     * @param ProfilerToolbars $profilerToolbars What a whole page gains: the debug meta and the toolbar (#231).
     */
    public function __construct(
        private readonly ProfileWriter $profileWriter,
        private readonly ResponseBuilder $responseBuilder,
        private readonly Monotonic $monotonic = new Monotonic(),
        private readonly ExchangeReader $exchangeReader = new ExchangeReader(),
        private readonly ProfilerToolbars $profilerToolbars = new ProfilerToolbars(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, before the framework's middleware.
     */
    #[Override]
    public function started(ServerRequestInterface $serverRequest): void
    {
        $this->recording = !str_starts_with($serverRequest->getUri()->getPath(), '/_profiler');
        $this->started = $this->monotonic->microseconds();
        $this->id = $this->profileWriter->newId();
    }

    /**
     * @param ServerRequestInterface  $serverRequest  The request as its handler gets it.
     * @param string                  $handler        The handler's class.
     * @param RequestHandlerInterface $requestHandler The kind's handler around it.
     */
    #[Override]
    public function routed(
        ServerRequestInterface $serverRequest,
        string $handler,
        RequestHandlerInterface $requestHandler,
    ): void {
        $this->routed = $this->monotonic->microseconds();
        $this->handler = $handler;
        $this->kind = $this->exchangeReader->kind($requestHandler);
        $this->user = $this->exchangeReader->user($serverRequest);
        $this->browser = $this->exchangeReader->browser($serverRequest);
    }

    /**
     * The handler answered.
     */
    #[Override]
    public function answered(): void
    {
        $this->answered = $this->monotonic->microseconds();
    }

    /**
     * @param string $path         The template's path.
     * @param string $component    Its class.
     * @param int    $microseconds Its time.
     */
    #[Override]
    public function rendered(string $path, string $component, int $microseconds): void
    {
        $this->components[] = new ComponentRecord($path, $component, $microseconds);
    }

    /**
     * @param int $microseconds One whole render's time.
     */
    #[Override]
    public function passed(int $microseconds): void
    {
        $this->render += $microseconds;
    }

    /**
     * @return Component|null Tessera's debug meta for a recorded whole page; null otherwise.
     */
    #[Override]
    public function headEnd(): ?Component
    {
        return $this->recording
            ? $this->profilerToolbars->debug()
            : null;
    }

    /**
     * @return Component|null The toolbar for a recorded whole page; null otherwise.
     */
    #[Override]
    public function bodyEnd(): ?Component
    {
        return $this->recording
            ? $this->profilerToolbars->toolbar($this->id, $this->browser)
            : null;
    }

    /**
     * @param StatementRecord $statementRecord A statement the request ran.
     */
    public function statement(StatementRecord $statementRecord): void
    {
        $this->statements[] = $statementRecord;
    }

    /**
     * @param ServerRequestInterface $serverRequest The request, as it started.
     * @param ResponseInterface      $response      What the app answered.
     *
     * @return ResponseInterface It, with `X-AIC-Profile` naming the kept profile; unchanged when nothing was kept.
     */
    #[Override]
    public function finished(ServerRequestInterface $serverRequest, ResponseInterface $response): ResponseInterface
    {
        if (!$this->recording) {
            return $response;
        }

        $requestFacts = $this->facts($serverRequest, $response);
        $id = $this->profileWriter->store($this->id, $requestFacts, $this->times(), $this->statements, $this->components);

        return $id === ''
            ? $response
            : $this->responseBuilder->withHeader($response, self::HEADER, $id);
    }

    /**
     * @param ServerRequestInterface $serverRequest The request.
     * @param ResponseInterface      $response      Its answer.
     *
     * @return RequestFacts What they were: the listed headers' values, and other headers' and fields' names only.
     */
    private function facts(ServerRequestInterface $serverRequest, ResponseInterface $response): RequestFacts
    {
        $tessera = [];

        foreach (self::TESSERA as $name) {
            $tessera[$name] = $serverRequest->getHeaderLine($name);
        }

        $answer = [];

        foreach (self::ANSWER as $name) {
            $answer[$name] = $response->getHeaderLine($name);
        }

        return new RequestFacts(
            $serverRequest->getMethod(),
            $serverRequest->getUri()->getPath(),
            $serverRequest->getUri()->getQuery(),
            $response->getStatusCode(),
            $this->handler,
            $this->user,
            $tessera,
            $answer,
            $response->getBody()->getSize() ?? 0,
            $this->exchangeReader->detail($serverRequest, $response, $this->kind, $this->browser),
        );
    }

    /**
     * @return ProfileTimes Where the time went, up to now.
     */
    private function times(): ProfileTimes
    {
        $finished = $this->monotonic->microseconds();
        $routed = $this->routed === 0
            ? $finished
            : $this->routed;
        $answered = $this->answered === 0
            ? $finished
            : $this->answered;

        return new ProfileTimes($finished - $this->started, $routed - $this->started, $answered - $routed, $this->render);
    }
}
