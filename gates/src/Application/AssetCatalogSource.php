<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Http\Contracts\PublicAsset;
use AIC\Http\Contracts\PublicAssets;
use InvalidArgumentException;
use Override;

use function array_chunk;
use function array_map;
use function count;
use function implode;
use function str_split;
use function strrev;
use function strtr;
use function var_export;

/** Plans a single lookup and bounded data partitions without duplicated constructor bodies. */
final readonly class AssetCatalogSource
{
    /** The lookup alone constructs its data chain; partition classes contain no constructors. */
    private const string LOOKUP = <<<'PHP'
        final readonly class Assets implements PublicAssets
        {
            /** @var list<PublicAsset> The declared representations, constructed from typed data partitions. */
            private array $publicAssets;

            /** @throws InvalidArgumentException When the compiled asset metadata is invalid. */
            public function __construct()
            {
                $this->publicAssets = {data};
            }

            /**
             * @param string $name A logical asset identifier.
             *
             * @return PublicAsset|null Its declared representation, or null.
             */
            #[Override]
            public function named(string $name): ?PublicAsset
            {
                return $this->find($name, true);
            }

            /**
             * @param string $path The exact canonical request path.
             *
             * @return PublicAsset|null Its declared representation, or null.
             */
            #[Override]
            public function byPath(string $path): ?PublicAsset
            {
                return $this->find($path, false);
            }

            /**
             * @param string $value  The requested identity.
             * @param bool   $byName Whether to match a logical name instead of a URL.
             *
             * @return PublicAsset|null The matching representation, or null.
             */
            private function find(string $value, bool $byName): ?PublicAsset
            {
                foreach ($this->publicAssets as $publicAsset) {
                    $selected = $this->selected($publicAsset, $value, $byName);

                    if ($selected !== null) {
                        return $selected;
                    }
                }

                return null;
            }

            /**
             * @param PublicAsset $publicAsset One declared representation.
             * @param string      $value       The requested identity.
             * @param bool        $byName      Whether the logical identity is requested.
             *
             * @return PublicAsset|null The representation when its selected identity matches.
             */
            private function selected(PublicAsset $publicAsset, string $value, bool $byName): ?PublicAsset
            {
                $matches = $byName
                    ? $publicAsset->name === $value
                    : $publicAsset->path === $value || $publicAsset->aliasPath === $value;

                return $matches
                    ? $publicAsset
                    : null;
            }
        }
        PHP;

    /** One data partition, with a direct typed continuation and no stored state. */
    private const string PART = <<<'PHP'
        final readonly class {class}
        {
            /**
             * @throws InvalidArgumentException When compiled asset metadata is invalid.
             *
             * @return list<PublicAsset> This partition's representations, then the next.
             */
            public function all(): array
            {
                return [
        {entries}{next}
                ];
            }
        }
        PHP;

    /** @param PhpClassFile $phpClassFile Writes gate-clean headers and imports. */
    public function __construct(private PhpClassFile $phpClassFile = new PhpClassFile())
    {
    }

    /**
     * @param list<PublicAsset> $assets The deterministic planned catalog.
     *
     * @throws InvalidArgumentException When a generated class cannot satisfy its structural limits.
     *
     * @return array<string, string> Generated basenames to complete PHP source; no files are written.
     */
    public function files(array $assets): array
    {
        $parts = array_chunk($assets, 1);
        $files = ['Assets.php' => $this->lookup($parts !== [])];

        foreach ($parts as $index => $entries) {
            $class = 'AssetsPart' . ($index + 1);
            $next = $index + 1 < count($parts)
                ? 'AssetsPart' . ($index + 2)
                : '';
            $files[$class . '.php'] = $this->part($class, $entries, $next);
        }

        return $files;
    }

    /**
     * @param bool $hasParts Whether at least one data partition exists.
     *
     * @throws InvalidArgumentException When the lookup exceeds its structural limits.
     *
     * @return string The root lookup source.
     */
    private function lookup(bool $hasParts): string
    {
        $data = $hasParts
            ? '(new AssetsPart1())->all()'
            : '[]';
        $body = strtr(self::LOOKUP, ['{data}' => $data]);
        $classes = [PublicAssets::class, PublicAsset::class, InvalidArgumentException::class, Override::class];

        if ($hasParts) {
            $first = 'AssetsPart1';
            $classes[] = 'App\\Generated\\' . $first;
        }

        $docblock = $this->phpClassFile->generated('the declared public asset catalog', 'the exact name and URL lookup.');

        return $this->phpClassFile->file('App\\Generated', $classes, [], $docblock, $body);
    }

    /**
     * @param string            $class  The generated partition class.
     * @param list<PublicAsset> $assets Its one entry, leaving room for exact canonical and alias metadata.
     * @param string            $next   The next partition class, or empty.
     *
     * @throws InvalidArgumentException When the partition exceeds structural limits.
     *
     * @return string The complete partition source.
     */
    private function part(string $class, array $assets, string $next): string
    {
        $continuation = $next === ''
            ? ''
            : "\n            ...(new " . $next . '())->all(),';
        $body = strtr(self::PART, [
            '{class}' => $class,
            '{entries}' => implode("\n", array_map($this->entry(...), $assets)),
            '{next}' => $continuation,
        ]);
        $classes = [PublicAsset::class, InvalidArgumentException::class];

        if ($next !== '') {
            $classes[] = 'App\\Generated\\' . $next;
        }

        $docblock = $this->phpClassFile->generated('the declared public asset catalog', 'one-entry data partition ' . $class . '.');

        return $this->phpClassFile->file('App\\Generated', $classes, [], $docblock, $body);
    }

    /**
     * @param PublicAsset $publicAsset The content-bound metadata.
     *
     * @return string Its typed constructor expression.
     */
    private function entry(PublicAsset $publicAsset): string
    {
        $values = [$publicAsset->name, $publicAsset->path, $publicAsset->file, $publicAsset->mediaType, $publicAsset->sha256];
        $arguments = array_map($this->literal(...), $values);
        $arguments[] = $this->integer($publicAsset->size);
        $arguments[] = $this->literal($publicAsset->aliasPath);

        return "            new PublicAsset(\n                " . implode(",\n                ", $arguments) . ",\n            ),";
    }

    /**
     * @param int $value A nonnegative byte count.
     *
     * @return string Its exact decimal literal with readable three-digit separators.
     */
    private function integer(int $value): string
    {
        return strrev(implode('_', str_split(strrev((string) $value), 3)));
    }

    /**
     * @param string $value One portable metadata string.
     *
     * @return string Its literal, split before a generated line could exceed the source policy.
     */
    private function literal(string $value): string
    {
        $parts = array_map(static fn (string $part): string => var_export($part, true), str_split($value, 80));

        return implode("\n                    . ", $parts);
    }
}
