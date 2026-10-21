<?php

declare(strict_types=1);

namespace AIC\Http\Domain;

use InvalidArgumentException;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriInterface;

use function array_filter;
use function array_is_list;
use function array_key_exists;
use function count;
use function is_array;
use function is_object;
use function is_scalar;
use function is_string;

use const ARRAY_FILTER_USE_KEY;

/**
 * The PSR-7 server request, with typed input.
 *
 * Query and form fields are strings, lists of strings or maps of strings
 * (InputParser); cookies are strings; server params are scalars; attributes are
 * scalars, objects or null. A PSR-7 wither given anything else narrows it to
 * those shapes or throws, so every getter returns typed values.
 */
final class ServerRequest extends Message implements ServerRequestInterface
{
    use RequestBehavior;

    /**
     * Uploaded files, one per field.
     *
     * @var array<string, UploadedFileInterface>
     */
    private array $uploadedFiles = [];

    /**
     * Attributes by name.
     *
     * @var array<string, scalar|object|null>
     */
    private array $attributes = [];

    /**
     * @param string                                                        $method          The request method.
     * @param UriInterface                                                  $uri             The request URI.
     * @param StreamInterface                                               $stream          The request body.
     * @param string                                                        $protocolVersion The HTTP version.
     * @param array<string, scalar>                                         $serverParams    The SAPI's server params.
     * @param array<string, string>                                         $cookieParams    The cookies.
     * @param array<string, string|list<string>|array<string, string>>      $queryParams     The query fields.
     * @param array<string, string|list<string>|array<string, string>>|null $parsedBody      The form fields, or null.
     *
     * @throws InvalidArgumentException When the method is invalid.
     */
    public function __construct(
        string $method,
        UriInterface $uri,
        StreamInterface $stream,
        string $protocolVersion = '1.1',
        private array $serverParams = [],
        private array $cookieParams = [],
        private array $queryParams = [],
        private array|object|null $parsedBody = null,
    ) {
        self::assertMethod($method);

        parent::__construct($protocolVersion, $stream);

        $this->method = $method;
        $this->uri = $uri;
        $this->initialHostFromUri($uri);
    }

    /**
     * @return array<string, scalar> The SAPI's server params.
     */
    #[Override]
    public function getServerParams(): array
    {
        return $this->serverParams;
    }

    /**
     * @return array<string, string> The cookies.
     */
    #[Override]
    public function getCookieParams(): array
    {
        return $this->cookieParams;
    }

    /**
     * @param array<array-key, mixed> $cookies The cookies for the copy; each a string.
     *
     * @throws MalformedInputException When a cookie is not a string.
     *
     * @return static A copy with those cookies.
     */
    #[Override]
    public function withCookieParams(array $cookies): static
    {
        $new = clone $this;
        $new->cookieParams = self::stringMap('cookie', array_filter($cookies, is_string(...)), count($cookies));

        return $new;
    }

    /**
     * @return array<string, string|list<string>|array<string, string>> The query fields.
     */
    #[Override]
    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    /**
     * @param array<array-key, mixed> $query The query fields for the copy, in the field shapes.
     *
     * @throws MalformedInputException When a field nests deeper than the field shapes.
     *
     * @return static A copy with those query fields.
     */
    #[Override]
    public function withQueryParams(array $query): static
    {
        $fields = [];

        foreach ($query as $name => $value) {
            $fields[(string) $name] = match (true) {
                is_string($value) => $value,
                is_array($value) => self::fieldGroup((string) $name, array_filter($value, is_string(...)), count($value)),
                default => throw new MalformedInputException('An input field must be a string or a group of strings: ' . $name),
            };
        }

        $new = clone $this;
        $new->queryParams = $fields;

        return $new;
    }

    /**
     * @return array<string, UploadedFileInterface> The uploads, one per field.
     */
    #[Override]
    public function getUploadedFiles(): array
    {
        return $this->uploadedFiles;
    }

    /**
     * @param array<array-key, mixed> $uploadedFiles The uploads for the copy, one per field.
     *
     * @throws MalformedInputException When an entry is not an uploaded file; a group of uploads
     *                                 per field (`files[]`) arrives with #8's multipart support.
     *
     * @return static A copy with those uploads.
     */
    #[Override]
    public function withUploadedFiles(array $uploadedFiles): static
    {
        $typed = [];

        foreach ($uploadedFiles as $field => $upload) {
            if (!$upload instanceof UploadedFileInterface) {
                throw new MalformedInputException('An upload must be one uploaded file per field: ' . $field);
            }

            $typed[(string) $field] = $upload;
        }

        $new = clone $this;
        $new->uploadedFiles = $typed;

        return $new;
    }

    /**
     * @return array<string, string|list<string>|array<string, string>>|object|null The form fields, or null.
     */
    #[Override]
    public function getParsedBody(): array|object|null
    {
        return $this->parsedBody;
    }

    /**
     * @param mixed $data Form fields in the field shapes, an object, or null.
     *
     * @throws InvalidArgumentException When the data is a scalar, or fields nest too deep.
     *
     * @return static A copy with that parsed body.
     */
    #[Override]
    public function withParsedBody(mixed $data): static
    {
        $new = clone $this;
        $new->parsedBody = match (true) {
            $data === null, is_object($data) => $data,
            is_array($data) => $this->withQueryParams($data)->queryParams,
            default => throw new InvalidArgumentException('A parsed body must be an array, an object or null.'),
        };

        return $new;
    }

    /**
     * @return array<string, scalar|object|null> The attributes.
     */
    #[Override]
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @param string $name    The attribute name.
     * @param mixed  $default The value when the attribute is not set.
     *
     * @return mixed The attribute, or the default.
     */
    #[Override]
    public function getAttribute(string $name, mixed $default = null): mixed
    {
        return array_key_exists($name, $this->attributes)
            ? $this->attributes[$name]
            : $default;
    }

    /**
     * @param string $name  The attribute name.
     * @param mixed  $value A scalar, an object or null; an array is refused.
     *
     * @throws InvalidArgumentException When the value is an array or a resource.
     *
     * @return static A copy with the attribute set.
     */
    #[Override]
    public function withAttribute(string $name, mixed $value): static
    {
        if (!is_scalar($value) && !is_object($value) && $value !== null) {
            throw new InvalidArgumentException('A request attribute must be a scalar, an object or null: ' . $name);
        }

        $new = clone $this;
        $new->attributes[$name] = $value;

        return $new;
    }

    /**
     * @param string $name The attribute name.
     *
     * @return static A copy without the attribute.
     */
    #[Override]
    public function withoutAttribute(string $name): static
    {
        $attributes = $this->attributes;
        unset($attributes[$name]);
        $new = clone $this;
        $new->attributes = $attributes;

        return $new;
    }

    /**
     * @param string                   $name    The field name, for the message.
     * @param array<array-key, string> $strings The group's string members.
     * @param int                      $total   How many members the group had.
     *
     * @throws MalformedInputException When a member is not a string, or the group is
     *                                 neither a list nor keyed by strings.
     *
     * @return list<string>|array<string, string> The group as a list or a map.
     */
    private static function fieldGroup(string $name, array $strings, int $total): array
    {
        if (count($strings) !== $total) {
            throw new MalformedInputException('An input field may nest one level of strings at most: ' . $name);
        }

        return array_is_list($strings) ? $strings
            : self::stringMap($name, $strings, $total);
    }

    /**
     * @param string                   $name    What the map holds, for the message.
     * @param array<array-key, string> $strings The string members.
     * @param int                      $total   How many members there were.
     *
     * @throws MalformedInputException When a member is not a string or its key is not a string.
     *
     * @return array<string, string> The members by name.
     */
    private static function stringMap(string $name, array $strings, int $total): array
    {
        $map = array_filter($strings, is_string(...), ARRAY_FILTER_USE_KEY);

        if (count($map) !== $total) {
            throw new MalformedInputException('Each ' . $name . ' must be a string under a string name.');
        }

        return $map;
    }
}
