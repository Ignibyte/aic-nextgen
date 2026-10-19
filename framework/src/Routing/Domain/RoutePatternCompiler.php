<?php

declare(strict_types=1);

namespace AIC\Routing\Domain;

use LogicException;

use function explode;
use function in_array;
use function preg_match;
use function str_contains;
use function strlen;
use function substr;

/**
 * Compiles a route's path pattern to an anchored regex.
 *
 * A placeholder is `{name}` or `{name:type}` (int, slug, uuid, any); it matches
 * one path segment and becomes a named group. Literal parts are quoted by hand:
 * elephc has no `preg_quote`.
 */
final readonly class RoutePatternCompiler
{
    /**
     * The regex delimiter; it never appears in a ParameterType regex.
     */
    private const string DELIMITER = '~';

    /**
     * The characters a regex literal must escape, with the delimiter.
     */
    private const string SPECIAL = '.\\+*?[^]$(){}=!<>|:-#~/';

    /**
     * A parameter name: a PCRE group name.
     */
    private const string NAME_PATTERN = '/^[a-zA-Z_]\\w*$/';

    /**
     * @param Route $route The route.
     *
     * @throws LogicException When a placeholder is unclosed, badly named, of an
     *                        unknown type, or repeated.
     *
     * @return CompiledRoute The route with its regex.
     */
    public function compile(Route $route): CompiledRoute
    {
        $regex = self::DELIMITER . '^';
        $names = [];

        foreach (explode('{', $route->path) as $index => $segment) {
            if ($index === 0) {
                $regex .= self::quoted($segment);
                continue;
            }

            $close = self::positionOf('}', $segment);
            $spec = self::spec($segment, $close, $route->path);
            $colon = self::positionOf(':', $spec);
            $name = self::name(substr($spec, 0, $colon), $names, $route->path);
            $names[] = $name;
            $type = self::type($colon === strlen($spec) ? 'any' : substr($spec, $colon + 1), $route->path);
            $regex .= '(?<' . $name . '>' . $type->regex() . ')' . self::quoted(substr($segment, $close + 1));
        }

        return new CompiledRoute($route, $regex . '$' . self::DELIMITER, $names);
    }

    /**
     * @param string $needle   One character.
     * @param string $haystack The text to look in.
     *
     * @return int The position of its first occurrence; the text's length when it has none.
     */
    private static function positionOf(string $needle, string $haystack): int
    {
        $length = strlen($haystack);

        for ($position = 0; $position < $length; $position += 1) {
            if ($haystack[$position] === $needle) {
                return $position;
            }
        }

        return $length;
    }

    /**
     * @param string $segment The text after a `{`.
     * @param int    $close   The position of its `}`; its length when it has none.
     * @param string $path    The whole pattern, for the message.
     *
     * @throws LogicException When the placeholder has no `}`.
     *
     * @return string The placeholder's text, `name` or `name:type`.
     */
    private static function spec(string $segment, int $close, string $path): string
    {
        if ($close === strlen($segment)) {
            throw new LogicException('An unclosed placeholder in route ' . $path);
        }

        return substr($segment, 0, $close);
    }

    /**
     * @param string       $name  The parameter name.
     * @param list<string> $names The names so far.
     * @param string       $path  The whole pattern, for the message.
     *
     * @throws LogicException When the name is not a group name or is repeated.
     *
     * @return string The name.
     */
    private static function name(string $name, array $names, string $path): string
    {
        if (preg_match(self::NAME_PATTERN, $name) !== 1 || in_array($name, $names, true)) {
            throw new LogicException('A bad or repeated parameter name "' . $name . '" in route ' . $path);
        }

        return $name;
    }

    /**
     * @param string $typeName The type after the `:`; `any` when none is given.
     * @param string $path     The whole pattern, for the message.
     *
     * @throws LogicException When the type is unknown.
     *
     * @return ParameterType The type.
     */
    private static function type(string $typeName, string $path): ParameterType
    {
        return match ($typeName) {
            ParameterType::Int->value => ParameterType::Int,
            ParameterType::Slug->value => ParameterType::Slug,
            ParameterType::Uuid->value => ParameterType::Uuid,
            ParameterType::Any->value => ParameterType::Any,
            default => throw new LogicException('An unknown parameter type "' . $typeName . '" in route ' . $path),
        };
    }

    /**
     * @param string $literal A literal part of the pattern.
     *
     * @return string It, with every regex-special character escaped.
     */
    private static function quoted(string $literal): string
    {
        $quoted = '';
        $length = strlen($literal);

        for ($position = 0; $position < $length; $position += 1) {
            $character = $literal[$position];
            $quoted .= str_contains(self::SPECIAL, $character)
                ? '\\' . $character
                : $character;
        }

        return $quoted;
    }
}
