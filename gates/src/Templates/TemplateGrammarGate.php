<?php

declare(strict_types=1);

namespace Gates\Templates;

use Gates\Application\AtomBuild;
use Gates\Application\PresentationBuild;
use Gates\Application\QuietFileReader;
use Gates\Domain\TemplateBinding;
use Gates\Domain\TemplateRefusal;
use Gates\Domain\TemplateViolation;
use InvalidArgumentException;

use function array_keys;
use function array_unique;
use function array_values;
use function count;
use function fwrite;
use function sprintf;
use function usort;

use const PHP_EOL;
use const STDERR;
use const STDOUT;

/**
 * Gate #25: the plain PHP template grammar (#96, part 1 of #88). Every
 * authored candidate is matched to its declaration; each refused construct
 * is one line, sorted by path and line. Each bound template is also held to
 * the HTML lint's markup and line rules (#170). It defers, named, when no
 * template exists.
 */
final readonly class TemplateGrammarGate
{
    /**
     * @param TemplateGrammar    $templateGrammar    The roots.
     * @param TemplateFinder     $templateFinder     Finds the templates.
     * @param TemplateValidator  $templateValidator  Checks each one.
     * @param QuietFileReader    $quietFileReader    Reads each one.
     * @param TemplateHtml       $templateHtml       Its markup rules (#170).
     * @param TemplateRoot       $templateRoot       One root element per component (#189).
     * @param TemplateWhitespace $templateWhitespace Its line rules (#170).
     */
    public function __construct(
        private TemplateGrammar $templateGrammar = new TemplateGrammar(),
        private TemplateFinder $templateFinder = new TemplateFinder(),
        private TemplateValidator $templateValidator = new TemplateValidator(),
        private QuietFileReader $quietFileReader = new QuietFileReader(),
        private TemplateHtml $templateHtml = new TemplateHtml(),
        private TemplateRoot $templateRoot = new TemplateRoot(),
        private TemplateWhitespace $templateWhitespace = new TemplateWhitespace(),
    ) {
    }

    /**
     * @param string      $root   The repo root, or the plant root.
     * @param string|null $theme  The theme the root's `config/app.php` names (#153); null when it names none.
     * @param resource    $stdout Where the success and deferral lines go.
     * @param resource    $stderr Where the violations go.
     *
     * @return int The exit code: 0 when every template follows the grammar, or none exists.
     */
    public function run(string $root, ?string $theme, mixed $stdout = STDOUT, mixed $stderr = STDERR): int
    {
        try {
            $bindings = $this->bindings($root, $theme);
            $templates = array_values(array_unique([
                ...$this->templateFinder->find($root, $this->templateGrammar->roots()),
                ...array_keys($bindings),
            ]));
            $violations = $this->violations($root, $templates, $bindings);
        } catch (InvalidArgumentException $invalidArgumentException) {
            fwrite($stderr, 'gate:25 — ' . $invalidArgumentException->getMessage() . PHP_EOL);

            return 1;
        }

        return $this->report($violations, count($templates), $stdout, $stderr);
    }

    /**
     * @param list<TemplateViolation> $violations Every refused source construct.
     * @param int                     $templates  Number of observed authored sources.
     * @param resource                $stdout     Success and deferral output.
     * @param resource                $stderr     Refusal output.
     *
     * @return int The actual grammar verdict.
     */
    private function report(array $violations, int $templates, mixed $stdout, mixed $stderr): int
    {
        if ($templates === 0) {
            fwrite($stdout, 'gate:25 — no authored templates under modules/, templates/ or themes/; deferring.' . PHP_EOL);

            return 0;
        }

        foreach ($violations as $violation) {
            fwrite($stderr, 'gate:25 — ' . $violation->message() . PHP_EOL);
        }

        $summary = $violations === []
            ? sprintf('gate:25 — %d template(s) follow the template grammar and the HTML lint.', $templates)
            : sprintf('gate:25 — %d violation(s) in %d template(s).', count($violations), $templates);
        fwrite($violations === [] ? $stdout : $stderr, $summary . PHP_EOL);

        return $violations === []
            ? 0
            : 1;
    }

    /**
     * @param string                         $root      The root.
     * @param list<string>                   $templates The templates, relative to it.
     * @param array<string, TemplateBinding> $bindings  The validated explicit mappings.
     *
     * @throws InvalidArgumentException When the fixed library catalog is invalid.
     *
     * @return list<TemplateViolation> Every violation, by path then line; an unreadable template is one.
     */
    private function violations(string $root, array $templates, array $bindings): array
    {
        $violations = [];

        foreach ($templates as $template) {
            $source = $this->quietFileReader->read($root . '/' . $template);
            $found = $this->sourceViolations($template, $source, $bindings[$template] ?? null);
            $violations = [...$violations, ...$found];
        }

        usort(
            $violations,
            static fn (TemplateViolation $one, TemplateViolation $other): int => [$one->path, $one->line] <=> [$other->path, $other->line],
        );

        return $violations;
    }

    /**
     * @param string               $path            The audited authored candidate.
     * @param string|null          $source          Its bytes, null when unreadable.
     * @param TemplateBinding|null $templateBinding Its actual declaration, absent for a refused candidate.
     *
     * @throws InvalidArgumentException When the fixed library catalog is invalid.
     *
     * @return list<TemplateViolation> Read/declaration refusals, or the declared source's grammar, markup and line violations.
     */
    private function sourceViolations(string $path, ?string $source, ?TemplateBinding $templateBinding): array
    {
        return match (true) {
            $source === null => [$this->unreadable($path)],
            $templateBinding === null => [new TemplateViolation(
                $path,
                1,
                TemplateRefusal::Placement,
                'a template lives in the configured theme: themes/<theme>/components/<Name>/<name>.phtml or themes/<theme>/layouts/',
            )],
            default => [
                ...$this->templateValidator->violations($path, $source, $templateBinding),
                ...$this->templateHtml->violations($path, $source),
                ...$this->templateRoot->violations($path, $source, $templateBinding),
                ...$this->templateWhitespace->violations($path, $source),
            ],
        };
    }

    /**
     * @param string      $root  The project or grammar plant folder.
     * @param string|null $theme The configured theme; null leaves only the framework's atoms bound.
     *
     * @throws InvalidArgumentException When the theme breaks its layout.
     *
     * @return array<string, TemplateBinding> The bindings; a candidate with none is refused.
     */
    private function bindings(string $root, ?string $theme): array
    {
        $bindings = (new AtomBuild(new PlainTemplateReader(), $root))->bindings();

        if ($theme === null) {
            return $bindings;
        }

        $presentationInventory = (new PresentationBuild($root))->plan($theme);

        foreach ($presentationInventory->templates as $binding) {
            $bindings[$binding->source] = $binding;
        }

        return $bindings;
    }

    /**
     * @param string $template A template that cannot be read.
     *
     * @return TemplateViolation Its violation: the gate fails closed.
     */
    private function unreadable(string $template): TemplateViolation
    {
        return new TemplateViolation($template, 1, TemplateRefusal::Syntax, 'cannot be read (fail-closed)');
    }
}
