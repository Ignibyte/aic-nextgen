<?php

declare(strict_types=1);

namespace AIC\Http\Domain;

use Override;
use Psr\Http\Message\UriInterface;

use function implode;
use function is_int;
use function is_string;
use function mb_strtolower;

/**
 * PSR-7 `UriInterface` implementation — RFC 3986 URI value object.
 *
 * Construction normalises per PSR-7 §4.3: scheme and host are lowercased; the
 * default port for the scheme (`80` for `http`, `443` for `https`) is
 * suppressed by storing `null`; path/query/fragment are percent-encoded over
 * the appropriate RFC 3986 character classes without double-encoding existing
 * triplets.
 *
 * The class is `final` but **not** `readonly`: PSR-7's `with*()`
 * methods rely on the `clone $this; $new->prop = $value;` idiom, which is the
 * universally-adopted pattern across nyholm/psr7, Slim, and Laminas Diactoros.
 * Effective immutability comes from `final` + no public setters; the only
 * mutators are the `with*()` clone idioms inside this class.
 */
final class Uri implements UriInterface
{
    use EncodesUriComponents;

    /** Unreserved + sub-delims + `:@` for paths (RFC 3986 §3.3). */
    private const string PATH_ALLOWED = "A-Za-z0-9_\\-.~!\\$&'()*+,;=:@\\/";

    /** Unreserved + sub-delims + `:@/?` for queries and fragments (RFC 3986 §3.4/§3.5). */
    private const string QUERY_ALLOWED = "A-Za-z0-9_\\-.~!\\$&'()*+,;=:@\\/?";

    /** Unreserved + sub-delims + `:` for the userinfo segment (RFC 3986 §3.2.1). */
    private const string USERINFO_ALLOWED = "A-Za-z0-9_\\-.~!\\$&'()*+,;=:";

    private string $scheme;

    private string $userInfo;

    private string $host;

    private ?int $port;

    private string $path;

    private string $query;

    private string $fragment;

    /**
     * Build a URI from already-split components.
     *
     * @param string   $scheme   URI scheme without the trailing `:` (e.g. `http`).
     * @param string   $userInfo Userinfo segment in the form `user[:password]`.
     * @param string   $host     Host (hostname, IPv4 literal, or `[IPv6]` literal).
     * @param int|null $port     Port number; `null` selects the scheme default.
     * @param string   $path     Absolute or relative path; will be percent-encoded.
     * @param string   $query    Query string without the leading `?`.
     * @param string   $fragment Fragment without the leading `#`.
     */
    public function __construct(
        string $scheme = '',
        string $userInfo = '',
        string $host = '',
        ?int $port = null,
        string $path = '',
        string $query = '',
        string $fragment = '',
    ) {
        $this->scheme = mb_strtolower($scheme);
        $this->userInfo = self::encode($userInfo, self::USERINFO_ALLOWED);
        $this->host = mb_strtolower($host);
        $this->port = self::filterPort($this->scheme, $port);
        $this->path = self::encode($path, self::PATH_ALLOWED);
        $this->query = self::encode($query, self::QUERY_ALLOWED);
        $this->fragment = self::encode($fragment, self::QUERY_ALLOWED);
    }

    /**
     * Reassemble the URI string from its components per RFC 3986 §5.3.
     */
    #[Override]
    public function __toString(): string
    {
        $authority = $this->getAuthority();
        $hasAuthority = $authority !== '';

        $candidates = [
            $this->scheme === '' ? '' : $this->scheme . ':',
            $hasAuthority || $this->scheme === 'file' ? '//' . $authority : '',
            self::normalizePath($this->path, $hasAuthority),
            $this->query === '' ? '' : '?' . $this->query,
            $this->fragment === '' ? '' : '#' . $this->fragment,
        ];

        return implode('', $candidates);
    }

    /**
     * @return string Lower-cased scheme component (RFC 3986 §3.1).
     */
    #[Override]
    public function getScheme(): string
    {
        return $this->scheme;
    }

    /**
     * @return string `[userinfo@]host[:port]` per RFC 3986 §3.2; empty when no host.
     */
    #[Override]
    public function getAuthority(): string
    {
        if ($this->host === '') {
            return '';
        }

        $authority = $this->host;

        if ($this->userInfo !== '') {
            $authority = $this->userInfo . '@' . $authority;
        }

        if (is_int($this->port)) {
            $authority .= ':' . $this->port;
        }

        return $authority;
    }

    /**
     * @return string `user[:password]` per RFC 3986 §3.2.1; empty when absent.
     */
    #[Override]
    public function getUserInfo(): string
    {
        return $this->userInfo;
    }

    /**
     * @return string Lower-cased host component (RFC 3986 §3.2.2); empty when absent.
     */
    #[Override]
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * @return int|null Port, or `null` when default for the scheme.
     */
    #[Override]
    public function getPort(): ?int
    {
        return $this->port;
    }

    /**
     * @return string Percent-encoded path (RFC 3986 §3.3).
     */
    #[Override]
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @return string Percent-encoded query without the leading `?`.
     */
    #[Override]
    public function getQuery(): string
    {
        return $this->query;
    }

    /**
     * @return string Percent-encoded fragment without the leading `#`.
     */
    #[Override]
    public function getFragment(): string
    {
        return $this->fragment;
    }

    /**
     * @param string $scheme Replacement scheme (lower-cased on assignment).
     */
    #[Override]
    public function withScheme(string $scheme): static
    {
        $normalized = mb_strtolower($scheme);

        if ($normalized === $this->scheme) {
            return $this;
        }

        $new = clone $this;
        $new->scheme = $normalized;
        $new->port = self::filterPort($normalized, $this->port);

        return $new;
    }

    /**
     * @param string      $user     Replacement user component (percent-encoded on assignment).
     * @param string|null $password Replacement password; `null` or empty omits it.
     */
    #[Override]
    public function withUserInfo(string $user, ?string $password = null): static
    {
        $info = $user;

        if (is_string($password) && $password !== '') {
            $info .= ':' . $password;
        }

        $encoded = self::encode($info, self::USERINFO_ALLOWED);

        if ($encoded === $this->userInfo) {
            return $this;
        }

        $new = clone $this;
        $new->userInfo = $encoded;

        return $new;
    }

    /**
     * @param string $host Replacement host (lower-cased on assignment).
     */
    #[Override]
    public function withHost(string $host): static
    {
        $normalized = mb_strtolower($host);

        if ($normalized === $this->host) {
            return $this;
        }

        $new = clone $this;
        $new->host = $normalized;

        return $new;
    }

    /**
     * @param int|null $port Replacement port; `null` clears it; default-port is suppressed.
     */
    #[Override]
    public function withPort(?int $port): static
    {
        $filtered = self::filterPort($this->scheme, $port);

        if ($filtered === $this->port) {
            return $this;
        }

        $new = clone $this;
        $new->port = $filtered;

        return $new;
    }

    /**
     * @param string $path Replacement path (percent-encoded on assignment).
     */
    #[Override]
    public function withPath(string $path): static
    {
        $encoded = self::encode($path, self::PATH_ALLOWED);

        if ($encoded === $this->path) {
            return $this;
        }

        $new = clone $this;
        $new->path = $encoded;

        return $new;
    }

    /**
     * @param string $query Replacement query (percent-encoded on assignment).
     */
    #[Override]
    public function withQuery(string $query): static
    {
        $encoded = self::encode($query, self::QUERY_ALLOWED);

        if ($encoded === $this->query) {
            return $this;
        }

        $new = clone $this;
        $new->query = $encoded;

        return $new;
    }

    /**
     * @param string $fragment Replacement fragment (percent-encoded on assignment).
     */
    #[Override]
    public function withFragment(string $fragment): static
    {
        $encoded = self::encode($fragment, self::QUERY_ALLOWED);

        if ($encoded === $this->fragment) {
            return $this;
        }

        $new = clone $this;
        $new->fragment = $encoded;

        return $new;
    }
}
