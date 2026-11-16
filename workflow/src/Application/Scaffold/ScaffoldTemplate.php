<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Scaffold;

use AIC\Workflow\Exception\WorkflowRefusal;

use function array_key_exists;
use function array_map;
use function array_slice;
use function explode;
use function implode;
use function in_array;
use function lcfirst;
use function preg_match_all;
use function preg_replace;
use function preg_replace_callback;
use function preg_split;
use function strtolower;
use function strtoupper;
use function trim;
use function ucfirst;

use const PREG_SET_ORDER;
use const PREG_SPLIT_NO_EMPTY;

/**
 * A scaffold template's grammar (#298): text with `{placeholder}` and
 * `{placeholder|filter|…}` and nothing else, no loops and no conditions, so a
 * template cannot compute. `{{` writes a literal `{`, for a route parameter
 * such as `/notes/{{id}`. The filters split a value into words (at `-`, `_`,
 * spaces and lower-to-upper case changes) and join them again.
 */
final readonly class ScaffoldTemplate
{
    /**
     * The filters a placeholder may name.
     */
    public const array FILTERS = ['camel', 'kebab', 'pascal', 'snake', 'upper'];

    /**
     * A literal `{`, or a placeholder with its filters.
     */
    private const string TOKEN = '/\\{\\{|\\{([a-z][a-zA-Z0-9]*)((?:\\|[a-z]+)*)\\}/';

    /**
     * @param string $text A template, a path pattern or a manifest value.
     *
     * @return list<array{string, list<string>}> Each placeholder in it, with its filters, in order.
     */
    public function placeholders(string $text): array
    {
        $found = [];
        preg_match_all(self::TOKEN, $text, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $name = $match[1] ?? '';

            if ($name === '') {
                continue;
            }

            $found[] = [$name, self::filters($match[2] ?? '')];
        }

        return $found;
    }

    /**
     * @param string $filter A filter's name.
     *
     * @return bool Whether a placeholder may name it.
     */
    public function knows(string $filter): bool
    {
        return in_array($filter, self::FILTERS, true);
    }

    /**
     * @param string                $text   A template, a path pattern or a manifest value.
     * @param array<string, string> $values Every placeholder's value.
     *
     * @throws WorkflowRefusal When a placeholder has no value or a filter is unknown; the loader refuses both first.
     *
     * @return string The text with every placeholder filled and every `{{` made `{`.
     */
    public function render(string $text, array $values): string
    {
        return (string) preg_replace_callback(
            self::TOKEN,
            fn (array $match): string => $this->token($match, $values),
            $text,
        );
    }

    /**
     * @param array<array-key, string> $match  The token: `{{`, or a placeholder with its filters.
     * @param array<string, string>    $values Every placeholder's value.
     *
     * @throws WorkflowRefusal When the placeholder has no value or a filter is unknown.
     *
     * @return string What it stands for.
     */
    private function token(array $match, array $values): string
    {
        $name = $match[1] ?? '';

        if ($name === '') {
            return '{';
        }

        if (!array_key_exists($name, $values)) {
            throw new WorkflowRefusal('the placeholder {' . $name . '} has no value.');
        }

        $value = $values[$name];

        foreach (self::filters($match[2] ?? '') as $filter) {
            $value = $this->filter($filter, $value);
        }

        return $value;
    }

    /**
     * @param string $filter A filter's name.
     * @param string $value  A value.
     *
     * @throws WorkflowRefusal When the filter is unknown.
     *
     * @return string The value through the filter.
     */
    private function filter(string $filter, string $value): string
    {
        $words = self::words($value);
        $pascal = implode('', array_map(ucfirst(...), $words));

        return match ($filter) {
            'camel' => lcfirst($pascal),
            'kebab' => implode('-', $words),
            'pascal' => $pascal,
            'snake' => implode('_', $words),
            'upper' => strtoupper(implode('_', $words)),
            default => throw new WorkflowRefusal('the filter |' . $filter . ' is not one of ' . implode(', ', self::FILTERS) . '.'),
        };
    }

    /**
     * @param string $filters A placeholder's filters, each after a `|`.
     *
     * @return list<string> Their names, in order.
     */
    private static function filters(string $filters): array
    {
        return $filters === ''
            ? []
            : array_slice(explode('|', $filters), 1);
    }

    /**
     * @param string $value A value, such as `StockPrices` or `stock-prices`.
     *
     * @return list<string> Its words in lower case, such as `stock` and `prices`.
     */
    private static function words(string $value): array
    {
        $spaced = (string) preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', ' ', $value);
        $words = preg_split('/[\\s_-]+/', trim($spaced), -1, PREG_SPLIT_NO_EMPTY);

        return array_map(strtolower(...), $words === false ? [] : $words);
    }
}
