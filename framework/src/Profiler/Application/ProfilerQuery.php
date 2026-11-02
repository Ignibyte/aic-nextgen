<?php

declare(strict_types=1);

namespace AIC\Profiler\Application;

use AIC\Profiler\Domain\ProfileFilter;
use Psr\Http\Message\ServerRequestInterface;

use function array_key_exists;
use function ctype_digit;
use function in_array;
use function is_string;

/**
 * Reads the profiler pages' GET parameters (#230), each checked: a value of
 * the wrong shape reads as none.
 */
final readonly class ProfilerQuery
{
    /** The kinds a filter may name. */
    private const array KINDS = ['Page', 'Api', 'Stream'];

    /**
     * @param ServerRequestInterface $serverRequest The list's request.
     *
     * @return ProfileFilter Its `path`, `kind` and `status`.
     */
    public function filter(ServerRequestInterface $serverRequest): ProfileFilter
    {
        $kind = $this->text($serverRequest, 'kind');
        $status = $this->text($serverRequest, 'status');

        return new ProfileFilter(
            $this->text($serverRequest, 'path'),
            in_array($kind, self::KINDS, true) ? $kind : '',
            $status !== '' && ctype_digit($status) ? (int) $status : 0,
        );
    }

    /**
     * @param ServerRequestInterface $serverRequest A profile's request.
     *
     * @return string Its `tab`, a known one; `request` otherwise.
     */
    public function tab(ServerRequestInterface $serverRequest): string
    {
        $tab = $this->text($serverRequest, 'tab');

        foreach (ProfilerDetailView::TABS as [$key]) {
            if ($key === $tab) {
                return $tab;
            }
        }

        return 'request';
    }

    /**
     * @param ServerRequestInterface $serverRequest A request.
     * @param string                 $name          A query parameter.
     *
     * @return string Its value when it is a string; empty otherwise.
     */
    private function text(ServerRequestInterface $serverRequest, string $name): string
    {
        $query = $serverRequest->getQueryParams();

        return array_key_exists($name, $query) && is_string($query[$name])
            ? $query[$name]
            : '';
    }
}
