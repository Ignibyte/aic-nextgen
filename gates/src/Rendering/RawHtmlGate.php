<?php

declare(strict_types=1);

namespace Gates\Rendering;

use AIC\Component\Application\Renderer;
use AIC\Component\Application\RenderPass;
use AIC\Component\Contracts\RawHtmlGrant;
use Gates\Domain\RawHtmlUse;
use InvalidArgumentException;
use PhpParser\Error;

use function array_filter;
use function array_key_exists;
use function count;
use function fwrite;
use function in_array;
use function sprintf;
use function str_starts_with;

use const PHP_EOL;
use const STDERR;
use const STDOUT;

/**
 * gate:27 — RawHtml, counted (#99): every `new RawHtml` is listed with its
 * place, its class and its grant's reason, and each module class that builds
 * one holds exactly the uses `config/raw-html.php` grants it. A use with no
 * grant, a count that differs, a grant with no use, a grant on a class that is
 * not a module's, and a `RawHtml::` reference outside the Renderer fail.
 */
final readonly class RawHtmlGate
{
    /**
     * The classes that may name RawHtml without building one: the Renderer and its pass.
     */
    private const array RENDERER = [Renderer::class, RenderPass::class];

    /**
     * @param RawHtmlScan   $rawHtmlScan   Finds the uses and references.
     * @param RawHtmlGrants $rawHtmlGrants Reads the grants.
     */
    public function __construct(
        private RawHtmlScan $rawHtmlScan = new RawHtmlScan(),
        private RawHtmlGrants $rawHtmlGrants = new RawHtmlGrants(),
    ) {
    }

    /**
     * @param string   $root   The repo root, or a plant root.
     * @param resource $stdout Where the list and the summary go.
     * @param resource $stderr Where the violations go.
     *
     * @throws Error When a file does not parse.
     *
     * @return int The exit code: 0 when every use is granted and every grant used.
     */
    public function run(string $root, mixed $stdout = STDOUT, mixed $stderr = STDERR): int
    {
        [$uses, $references] = $this->rawHtmlScan->scan($root);

        try {
            $grants = $this->rawHtmlGrants->read($root);
        } catch (InvalidArgumentException $invalidArgumentException) {
            fwrite($stderr, 'gate:27 — config/raw-html.php: ' . $invalidArgumentException->getMessage() . PHP_EOL);

            return 1;
        }

        $violations = self::violations($uses, $references, $grants);
        self::report($uses, $grants, $stdout);

        foreach ($violations as $violation) {
            fwrite($stderr, 'gate:27 — ' . $violation . PHP_EOL);
        }

        $summary = sprintf('gate:27 — %d RawHtml use(s), %d grant(s), %d violation(s).', count($uses), count($grants), count($violations));
        fwrite($violations === [] ? $stdout : $stderr, $summary . PHP_EOL);

        return $violations === []
            ? 0
            : 1;
    }

    /**
     * @param list<RawHtmlUse>            $uses       Every `new RawHtml`.
     * @param list<RawHtmlUse>            $references Every `RawHtml::` reference.
     * @param array<string, RawHtmlGrant> $grants     The grants, by class.
     *
     * @return list<string> Every violation: ungranted uses, miscounted grants, grants outside the theme's components, references.
     */
    private static function violations(array $uses, array $references, array $grants): array
    {
        return [
            ...self::ungranted($uses, $grants),
            ...self::miscounted($uses, $grants),
            ...self::foreign($grants),
            ...self::outside($references),
        ];
    }

    /**
     * @param list<RawHtmlUse>            $uses   Every `new RawHtml`.
     * @param array<string, RawHtmlGrant> $grants The grants, by class.
     * @param resource                    $stdout Where the list goes.
     */
    private static function report(array $uses, array $grants, mixed $stdout): void
    {
        foreach ($uses as $use) {
            $because = array_key_exists($use->class, $grants)
                ? $grants[$use->class]->because
                : 'no grant';
            fwrite($stdout, 'gate:27 — ' . $use->place() . ' ' . $use->class . ': ' . $because . PHP_EOL);
        }
    }

    /**
     * @param list<RawHtmlUse>            $uses   Every `new RawHtml`.
     * @param array<string, RawHtmlGrant> $grants The grants, by class.
     *
     * @return list<string> A line per use with no grant.
     */
    private static function ungranted(array $uses, array $grants): array
    {
        $violations = [];

        foreach ($uses as $use) {
            if (array_key_exists($use->class, $grants)) {
                continue;
            }

            $violations[] = $use->place() . ': RawHtml in ' . $use->class . ' has no grant in config/raw-html.php.';
        }

        return $violations;
    }

    /**
     * @param list<RawHtmlUse>            $uses   Every `new RawHtml`.
     * @param array<string, RawHtmlGrant> $grants The grants, by class.
     *
     * @return list<string> A line per granted class whose uses differ from its grant, none counting as a difference.
     */
    private static function miscounted(array $uses, array $grants): array
    {
        $violations = [];

        foreach ($grants as $class => $grant) {
            $count = count(array_filter($uses, static fn (RawHtmlUse $rawHtmlUse): bool => $rawHtmlUse->class === $class));

            if ($count === $grant->uses) {
                continue;
            }

            $violations[] = sprintf('%s builds RawHtml %d time(s); its grant says %d.', $class, $count, $grant->uses);
        }

        return $violations;
    }

    /**
     * @param array<string, RawHtmlGrant> $grants The grants, by class.
     *
     * @return list<string> A line per grant on a class that is not a theme component's.
     */
    private static function foreign(array $grants): array
    {
        $violations = [];

        foreach ($grants as $class => $grant) {
            if (str_starts_with($class, 'App\\Theme\\Components\\')) {
                continue;
            }

            $violations[] = 'config/raw-html.php grants ' . $grant->class . ', not a theme component class: only a theme component may '
                . 'build RawHtml, since a module holds no markup (#156).';
        }

        return $violations;
    }

    /**
     * @param list<RawHtmlUse> $references Every `RawHtml::` reference.
     *
     * @return list<string> A line per reference outside the Renderer: a class-string could build RawHtml unseen.
     */
    private static function outside(array $references): array
    {
        $violations = [];

        foreach ($references as $reference) {
            if (in_array($reference->class, self::RENDERER, true)) {
                continue;
            }

            $violations[] = $reference->place() . ': RawHtml:: is named outside the Renderer; build it with new, under a grant.';
        }

        return $violations;
    }
}
