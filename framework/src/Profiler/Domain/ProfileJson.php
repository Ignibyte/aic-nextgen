<?php

declare(strict_types=1);

namespace AIC\Profiler\Domain;

use function implode;
use function json_encode;

use const JSON_INVALID_UTF8_SUBSTITUTE;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * A profile as the JSON `aic_profiles.profile` holds (#229): built member by
 * member from typed values, so every encoded value is one string or one
 * integer. The pages (#230) read this shape.
 */
final readonly class ProfileJson
{
    /** How each string is encoded: kept readable, a bad byte replaced rather than failing the request. */
    private const int FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE;

    /**
     * @param RequestFacts          $requestFacts The request and its answer.
     * @param ProfileTimes          $profileTimes Where its time went.
     * @param list<StatementRecord> $statements   Its statements, in order.
     * @param list<ComponentRecord> $components   Its templates, in the order each finished.
     *
     * @return string The profile.
     */
    public function encode(
        RequestFacts $requestFacts,
        ProfileTimes $profileTimes,
        array $statements,
        array $components,
    ): string {
        return $this->object([
            'answer' => $this->texts($requestFacts->answer),
            'bytes' => (string) $requestFacts->bytes,
            'components' => $this->components($components),
            'handler' => $this->text($requestFacts->handler),
            'method' => $this->text($requestFacts->method),
            'microseconds' => $this->times($profileTimes),
            'path' => $this->text($requestFacts->path),
            'query' => $this->text($requestFacts->query),
            'statements' => $this->statements($statements),
            'status' => (string) $requestFacts->status,
            'tessera' => $this->texts($requestFacts->tessera),
            'user' => $this->text($requestFacts->user),
        ]);
    }

    /**
     * @param ProfileTimes $profileTimes Where the time went.
     *
     * @return string Them as an object.
     */
    private function times(ProfileTimes $profileTimes): string
    {
        return $this->object([
            'handler' => (string) $profileTimes->handler,
            'middleware' => (string) $profileTimes->middleware,
            'render' => (string) $profileTimes->render,
            'total' => (string) $profileTimes->total,
        ]);
    }

    /**
     * @param list<StatementRecord> $statements The statements.
     *
     * @return string Them as a list of objects.
     */
    private function statements(array $statements): string
    {
        $items = [];

        foreach ($statements as $statement) {
            $items[] = $this->object([
                'microseconds' => (string) $statement->microseconds,
                'rows' => (string) $statement->rows,
                'sql' => $this->text($statement->sql),
            ]);
        }

        return '[' . implode(',', $items) . ']';
    }

    /**
     * @param list<ComponentRecord> $components The templates.
     *
     * @return string Them as a list of objects.
     */
    private function components(array $components): string
    {
        $items = [];

        foreach ($components as $component) {
            $items[] = $this->object([
                'component' => $this->text($component->component),
                'microseconds' => (string) $component->microseconds,
                'path' => $this->text($component->path),
            ]);
        }

        return '[' . implode(',', $items) . ']';
    }

    /**
     * @param array<string, string> $values Strings by name.
     *
     * @return string Them as an object of strings.
     */
    private function texts(array $values): string
    {
        $members = [];

        foreach ($values as $name => $value) {
            $members[$name] = $this->text($value);
        }

        return $this->object($members);
    }

    /**
     * @param array<string, string> $members Each member's encoded value, by name.
     *
     * @return string The object.
     */
    private function object(array $members): string
    {
        $pairs = [];

        foreach ($members as $name => $value) {
            $pairs[] = $this->text($name) . ':' . $value;
        }

        return '{' . implode(',', $pairs) . '}';
    }

    /**
     * @param string $value A string.
     *
     * @return string It as a JSON string; an empty one in the case the encoder still refuses.
     */
    private function text(string $value): string
    {
        $encoded = json_encode($value, self::FLAGS);

        return $encoded === false
            ? '""'
            : $encoded;
    }
}
