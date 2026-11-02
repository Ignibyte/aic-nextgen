<?php

declare(strict_types=1);

namespace AIC\Profiler\Application;

use AIC\Controller\Domain\EventStream;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The toolbar's live list as a response (#231): `/_profiler/stream`, this
 * browser's exchanges from the page's own profile on, resumed after the last
 * one Tessera saw.
 */
final readonly class ProfilerFeed
{
    /** Where the pages serve it. */
    public const string PATH = '/_profiler/stream';

    /**
     * @param ProfileReader $profileReader Reads the kept profiles.
     * @param ProfilerQuery $profilerQuery Reads the stream's parameters.
     */
    public function __construct(
        private ProfileReader $profileReader,
        private ProfilerQuery $profilerQuery = new ProfilerQuery(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The stream's request.
     *
     * @return EventStream The stream.
     */
    public function stream(ServerRequestInterface $serverRequest): EventStream
    {
        return new EventStream(new ProfilerStream(
            $this->profileReader,
            $this->profilerQuery->browser($serverRequest),
            $this->profilerQuery->after($serverRequest),
            $this->profilerQuery->resumed($serverRequest),
        ));
    }
}
