<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Workflow\Application\Console\ConsoleArguments;
use AIC\Workflow\Application\Make\Source\StateSource;
use AIC\Workflow\Domain\Console\ParsedArguments;
use AIC\Workflow\Domain\Make\MadeFile;
use AIC\Workflow\Domain\Make\MakeRecipe;
use AIC\Workflow\Domain\Make\StateTransition;
use AIC\Workflow\Exception\WorkflowRefusal;

use function array_diff;
use function array_filter;
use function array_unique;
use function count;
use function explode;
use function preg_match;
use function preg_replace;
use function str_replace;
use function ucwords;

/**
 * What `make:state` makes (#308): it reads the line's states and
 * transitions and gives the recipe, the enum, the transitions class and the
 * migration with its manifest call and the lines to add by hand.
 */
final readonly class MakeStates
{
    /**
     * @param MakeNames   $makeNames   The rules.
     * @param MakeCalls   $makeCalls   The migration call.
     * @param StateSource $stateSource Writes the files and the lines.
     */
    public function __construct(
        private MakeNames $makeNames = new MakeNames(),
        private MakeCalls $makeCalls = new MakeCalls(),
        private StateSource $stateSource = new StateSource(),
    ) {
    }

    /**
     * @param ParsedArguments $parsedArguments The line.
     * @param MakeVersions    $makeVersions    The migration's version.
     *
     * @throws WorkflowRefusal When a name, a state or a transition breaks a rule.
     *
     * @return MakeRecipe The enum, the machine and the migration, its manifest line, and the declarations to add.
     */
    public function recipe(ParsedArguments $parsedArguments, MakeVersions $makeVersions): MakeRecipe
    {
        $module = $parsedArguments->positional(0);
        $namespace = $this->makeNames->module($module);
        $place = [
            'field' => $this->makeNames->word($parsedArguments->positional(2), 'table'),
            'table' => $this->makeNames->word($parsedArguments->positional(1), 'table'),
        ];
        $states = $this->states($parsedArguments->flag('states'));
        $transitions = $this->transitions($parsedArguments->flag('transitions'), $states);
        $singular = preg_replace(['/ies$/', '/s$/'], ['y', ''], $place['table']) ?? $place['table'];
        $enum = self::pascal($singular . '_' . $place['field']);
        $migration = 'Add' . self::pascal($place['table'] . '_' . $place['field']);
        $names = ['class' => $enum . 'Transitions', 'enum' => $enum, 'migration' => $migration];
        $version = $makeVersions->version($parsedArguments, 'add_' . $place['table'] . '_' . $place['field']);
        $manifestCall = $this->makeCalls->classOnly('migration', 'Migration', $names['migration'], $namespace);
        $files = $this->files('modules/' . $module . '/src/', $namespace, $names, $place, $states, $transitions, $version);

        $lines = $this->stateSource->lines($names['class'], $place, $states);

        return new MakeRecipe($module, $names['migration'], $files, $manifestCall, lines: $lines);
    }

    /**
     * @param string                                                $folder      The module's `src/`.
     * @param string                                                $namespace   The module's namespace.
     * @param array{class: string, enum: string, migration: string} $names       The three classes.
     * @param array{table: string, field: string}                   $place       Where the state lives.
     * @param list<string>                                          $states      The states, the initial one first.
     * @param list<StateTransition>                                 $transitions The transitions.
     * @param string                                                $version     The migration's version.
     *
     * @throws WorkflowRefusal When a template is missing.
     *
     * @return list<MadeFile> The enum, the transitions class and the migration.
     */
    private function files(
        string $folder,
        string $namespace,
        array $names,
        array $place,
        array $states,
        array $transitions,
        string $version,
    ): array {
        $classes = ['class' => $names['class'], 'enum' => $names['enum']];
        $machine = $this->stateSource->machine($namespace, $classes, $place, $states, $transitions);
        $enum = $this->stateSource->enum($namespace, $names['enum'], $place, $states);
        $migration = $this->stateSource->migration($namespace, $names['migration'], $version, $place, $states);

        return [
            new MadeFile($folder . 'Entity/' . $names['enum'] . '.php', $enum),
            new MadeFile($folder . 'Entity/' . $names['class'] . '.php', $machine),
            new MadeFile($folder . 'Migration/' . $names['migration'] . '.php', $migration),
        ];
    }

    /**
     * @param string $states What `--states` gave.
     *
     * @throws WorkflowRefusal When there is none, one repeats, or one is not lower case.
     *
     * @return list<string> The states, in order.
     */
    private function states(string $states): array
    {
        $list = explode(',', $states);
        $words = array_filter($list, static fn (string $state): bool => preg_match('/^[a-z][a-z0-9_]{0,62}$/', $state) === 1);

        if (count($words) !== count($list) || count(array_unique($list)) !== count($list)) {
            throw new WorkflowRefusal(
                '--states takes lower-case states, none twice, the first where a row starts: --states=open,closed.',
                ConsoleArguments::USAGE,
            );
        }

        return $list;
    }

    /**
     * @param string       $transitions What `--transitions` gave.
     * @param list<string> $states      The states.
     *
     * @throws WorkflowRefusal When there is none, one is malformed or named `machine`, or one names a state not among them.
     *
     * @return list<StateTransition> The transitions, in order.
     */
    private function transitions(string $transitions, array $states): array
    {
        $parsed = [];

        foreach (explode(',', $transitions) as $transition) {
            $parsed[] = $this->transition($transition, $states);
        }

        return $parsed;
    }

    /**
     * @param string       $transition One `--transitions` entry, `name:from|from>to@permission`.
     * @param list<string> $states     The states.
     *
     * @throws WorkflowRefusal When it is malformed or named `machine`, or names a state not among them.
     *
     * @return StateTransition It.
     */
    private function transition(string $transition, array $states): StateTransition
    {
        $named = explode(':', $transition, 2);
        $edge = explode('@', $named[1] ?? '', 2);
        $moves = explode('>', $edge[0], 2);
        $leaves = explode('|', $moves[0]);
        $into = $moves[1] ?? '';
        $permission = $edge[1] ?? '';
        $words = preg_match('/^[a-z][a-z0-9_]*$/', $named[0]) === 1 && preg_match('/^[a-z0-9._-]*$/', $permission) === 1;

        if (!$words || $named[0] === 'machine' || array_diff([...$leaves, $into], $states) !== []) {
            throw new WorkflowRefusal(
                '--transitions takes name:from|from>to@permission over the states, the permission optional; "'
                    . $transition . '" is not one.',
                ConsoleArguments::USAGE,
            );
        }

        return new StateTransition($named[0], $leaves, $into, $permission);
    }

    /**
     * @param string $snake A snake-case name.
     *
     * @return string It in PascalCase.
     */
    private static function pascal(string $snake): string
    {
        return str_replace('_', '', ucwords($snake, '_'));
    }
}
