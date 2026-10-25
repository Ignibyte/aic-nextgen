<?php

declare(strict_types=1);

namespace Gates\Templates;

use Gates\Application\ModuleBuild;
use Gates\Application\PresentationBuild;
use Gates\Application\QuietFileReader;
use Gates\Domain\TemplateBinding;
use Gates\Domain\TemplateRefusal;
use Gates\Domain\TemplateViolation;
use InvalidArgumentException;

use function count;
use function fwrite;
use function glob;
use function sprintf;
use function usort;

use const PHP_EOL;
use const STDERR;
use const STDOUT;

/**
 * Gate #25: the plain PHP template grammar (#96, part 1 of #88). Every
 * `*.html.php` under the grammar's roots is validated; each refused construct
 * is one line, sorted by path and line. It defers, named, when no template
 * exists.
 */
final readonly class TemplateGrammarGate
{
    /**
     * @param TemplateGrammar   $templateGrammar   The roots.
     * @param TemplateFinder    $templateFinder    Finds the templates.
     * @param TemplateValidator $templateValidator Checks each one.
     * @param QuietFileReader   $quietFileReader   Reads each one.
     */
    public function __construct(
        private TemplateGrammar $templateGrammar = new TemplateGrammar(),
        private TemplateFinder $templateFinder = new TemplateFinder(),
        private TemplateValidator $templateValidator = new TemplateValidator(),
        private QuietFileReader $quietFileReader = new QuietFileReader(),
    ) {
    }

    /**
     * @param string   $root   The repo root, or the plant root.
     * @param resource $stdout Where the success and deferral lines go.
     * @param resource $stderr Where the violations go.
     *
     * @return int The exit code: 0 when every template follows the grammar, or none exists.
     */
    public function run(string $root, mixed $stdout = STDOUT, mixed $stderr = STDERR): int
    {
        try {
            $bindings = $this->bindings($root);
            $templates = $this->templateFinder->find($root, $this->templateGrammar->roots());
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
            ? sprintf('gate:25 — %d template(s) follow the template grammar.', $templates)
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
     * @return list<TemplateViolation> Every violation, by path then line; an unreadable template is one.
     */
    private function violations(string $root, array $templates, array $bindings): array
    {
        $violations = [];

        foreach ($templates as $template) {
            $source = $this->quietFileReader->read($root . '/' . $template);
            $found = $source === null
                ? [$this->unreadable($template)]
                : $this->templateValidator->violations($template, $source, $bindings[$template] ?? null);
            $violations = [...$violations, ...$found];
        }

        usort(
            $violations,
            static fn (TemplateViolation $one, TemplateViolation $other): int => [$one->path, $one->line] <=> [$other->path, $other->line],
        );

        return $violations;
    }

    /**
     * @param string $root The project or grammar plant folder.
     *
     * @throws InvalidArgumentException When real module declarations or their sources are invalid.
     *
     * @return array<string, TemplateBinding> Explicit bindings, absent for manifest-free legacy grammar plants.
     */
    private function bindings(string $root): array
    {
        $manifests = glob($root . '/modules/*/*.module.php');

        if ($manifests === false || $manifests === []) {
            return [];
        }

        $presentationInventory = (new PresentationBuild($root))->plan((new ModuleBuild($root))->modules());
        $bindings = [];

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
