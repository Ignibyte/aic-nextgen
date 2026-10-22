<?php

declare(strict_types=1);

namespace Gates\Templates;

use Gates\Application\QuietFileReader;
use Gates\Domain\TemplateRefusal;
use Gates\Domain\TemplateViolation;

use function count;
use function fwrite;
use function sprintf;
use function usort;

use const PHP_EOL;
use const STDERR;
use const STDOUT;

/**
 * Gate #25: the plain PHP template grammar (#96, part 1 of #88). Every
 * `*.html.php` under the grammar's roots is validated; each refused construct
 * is one line, sorted by path and line. It defers, named, while no template
 * exists (until #97 converts the `.tpl` templates).
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
        $templates = $this->templateFinder->find($root, $this->templateGrammar->roots());

        if ($templates === []) {
            fwrite($stdout, 'gate:25 — no component templates (*.html.php) under modules/ or templates/ yet; deferring.' . PHP_EOL);

            return 0;
        }

        $violations = $this->violations($root, $templates);

        foreach ($violations as $violation) {
            fwrite($stderr, 'gate:25 — ' . $violation->message() . PHP_EOL);
        }

        $summary = $violations === []
            ? sprintf('gate:25 — %d template(s) follow the template grammar.', count($templates))
            : sprintf('gate:25 — %d violation(s) in %d template(s).', count($violations), count($templates));
        fwrite($violations === [] ? $stdout : $stderr, $summary . PHP_EOL);

        return $violations === []
            ? 0
            : 1;
    }

    /**
     * @param string       $root      The root.
     * @param list<string> $templates The templates, relative to it.
     *
     * @return list<TemplateViolation> Every violation, by path then line; an unreadable template is one.
     */
    private function violations(string $root, array $templates): array
    {
        $violations = [];

        foreach ($templates as $template) {
            $source = $this->quietFileReader->read($root . '/' . $template);
            $found = $source === null
                ? [$this->unreadable($template)]
                : $this->templateValidator->violations($template, $source);
            $violations = [...$violations, ...$found];
        }

        usort(
            $violations,
            static fn (TemplateViolation $one, TemplateViolation $other): int => [$one->path, $one->line] <=> [$other->path, $other->line],
        );

        return $violations;
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
