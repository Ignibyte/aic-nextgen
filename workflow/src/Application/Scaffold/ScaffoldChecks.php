<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Scaffold;

use AIC\Workflow\Domain\Scaffold\ArgType;
use AIC\Workflow\Domain\Scaffold\ScaffoldArg;
use AIC\Workflow\Domain\Scaffold\ScaffoldManifest;
use AIC\Workflow\Exception\WorkflowRefusal;

use function array_diff;
use function array_filter;
use function array_keys;
use function array_map;
use function implode;
use function in_array;
use function preg_match;
use function sprintf;

/**
 * The checks the loader runs on a scaffold's parts (#298): each argument's
 * name, choices and default, the probe values, and every placeholder and
 * filter in a path, a template or a manifest value.
 */
final readonly class ScaffoldChecks
{
    /**
     * @param ScaffoldTemplate $scaffoldTemplate The template grammar.
     * @param ScaffoldValues   $scaffoldValues   The argument types.
     */
    public function __construct(
        private ScaffoldTemplate $scaffoldTemplate = new ScaffoldTemplate(),
        private ScaffoldValues $scaffoldValues = new ScaffoldValues(),
    ) {
    }

    /**
     * @param string           $where            The manifest's path, for the refusal.
     * @param ScaffoldManifest $scaffoldManifest The manifest.
     *
     * @throws WorkflowRefusal When an argument's name, choices or default breaks a rule.
     *
     * @return list<string> Every placeholder the scaffold may use.
     */
    public function args(string $where, ScaffoldManifest $scaffoldManifest): array
    {
        $known = ScaffoldValues::BUILT_IN;

        foreach ($scaffoldManifest->args as $scaffoldArg) {
            $this->arg($where, $scaffoldArg, $known);
            $known[] = $scaffoldArg->name;
        }

        return $known;
    }

    /**
     * @param string           $where            The manifest's path, for the refusal.
     * @param ScaffoldManifest $scaffoldManifest The manifest.
     *
     * @throws WorkflowRefusal When the probe lacks the name or an argument without a default, names an unknown one, or
     *                         a value breaks its rule.
     */
    public function probe(string $where, ScaffoldManifest $scaffoldManifest): void
    {
        $this->probeKeys($where, $scaffoldManifest);
        $flags = $scaffoldManifest->probe;
        unset($flags['name']);
        $this->scaffoldValues->values($scaffoldManifest, 'probe', $scaffoldManifest->probe['name'] ?? '', $flags);
    }

    /**
     * @param string       $where Where the text is, for the refusal.
     * @param string       $text  A path pattern, a template or a manifest value.
     * @param list<string> $known The placeholders it may use.
     *
     * @throws WorkflowRefusal When it names a placeholder or a filter that is not known.
     */
    public function known(string $where, string $text, array $known): void
    {
        foreach ($this->scaffoldTemplate->placeholders($text) as [$name, $filters]) {
            $this->placeholder($where, $name, $filters, $known);
        }
    }

    /**
     * @param string       $where       The manifest's path, for the refusal.
     * @param ScaffoldArg  $scaffoldArg An argument.
     * @param list<string> $known       The placeholders before it.
     *
     * @throws WorkflowRefusal When its name, choices or default breaks a rule.
     */
    private function arg(string $where, ScaffoldArg $scaffoldArg, array $known): void
    {
        $named = preg_match('/^[a-z][a-zA-Z0-9]*$/', $scaffoldArg->name) === 1;

        if (!$named || in_array($scaffoldArg->name, $known, true)) {
            throw new WorkflowRefusal(
                $where . ': the argument ' . $scaffoldArg->name . ' must be lower camel case, once, and not module, name or namespace.',
            );
        }

        $this->choices($where, $scaffoldArg);

        if ($scaffoldArg->default === null) {
            return;
        }

        $this->scaffoldValues->checked($scaffoldArg, $scaffoldArg->default);
    }

    /**
     * @param string      $where       The manifest's path, for the refusal.
     * @param ScaffoldArg $scaffoldArg An argument.
     *
     * @throws WorkflowRefusal When a choice has no choices or one breaks the word rule, or another type has some.
     */
    private function choices(string $where, ScaffoldArg $scaffoldArg): void
    {
        if (($scaffoldArg->argType === ArgType::Choice) !== ($scaffoldArg->choices !== [])) {
            throw new WorkflowRefusal($where . ': the argument ' . $scaffoldArg->name . ' lists choices only when its type is choice.');
        }

        $bad = array_filter($scaffoldArg->choices, static fn (string $choice): bool => preg_match('/^[A-Za-z0-9_-]+$/', $choice) !== 1);

        if ($bad !== []) {
            throw new WorkflowRefusal($where . ': the choices ' . implode(', ', $bad) . ' must be letters, digits, _ and -.');
        }
    }

    /**
     * @param string           $where            The manifest's path, for the refusal.
     * @param ScaffoldManifest $scaffoldManifest The manifest.
     *
     * @throws WorkflowRefusal When the probe lacks the name or an argument without a default, or names an unknown one.
     */
    private function probeKeys(string $where, ScaffoldManifest $scaffoldManifest): void
    {
        $given = array_keys($scaffoldManifest->probe);
        $args = array_map(static fn (ScaffoldArg $scaffoldArg): string => $scaffoldArg->name, $scaffoldManifest->args);
        $required = array_filter($scaffoldManifest->args, static fn (ScaffoldArg $scaffoldArg): bool => $scaffoldArg->default === null);
        $unknown = array_diff($given, ['name', ...$args]);
        $missing = array_diff(
            ['name', ...array_map(static fn (ScaffoldArg $scaffoldArg): string => $scaffoldArg->name, $required)],
            $given,
        );

        if ($unknown !== [] || $missing !== []) {
            throw new WorkflowRefusal(sprintf(
                '%s: ->probe([...]) gives the name and each argument without a default, and nothing else (unknown: %s; missing: %s).',
                $where,
                implode(', ', $unknown),
                implode(', ', $missing),
            ));
        }
    }

    /**
     * @param string       $where   Where the text is, for the refusal.
     * @param string       $name    A placeholder's name.
     * @param list<string> $filters Its filters.
     * @param list<string> $known   The placeholders it may use.
     *
     * @throws WorkflowRefusal When the name or a filter is not known.
     */
    private function placeholder(string $where, string $name, array $filters, array $known): void
    {
        if (!in_array($name, $known, true)) {
            throw new WorkflowRefusal(sprintf('%s: {%s} is not an argument (write {{ for a literal {).', $where, $name));
        }

        $unknown = array_filter($filters, fn (string $filter): bool => !$this->scaffoldTemplate->knows($filter));

        if ($unknown !== []) {
            throw new WorkflowRefusal(sprintf("%s: the filter |%s is not one of the engine's.", $where, implode(', |', $unknown)));
        }
    }
}
