<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make\Source;

use AIC\Workflow\Application\Make\MakeTemplates;
use AIC\Workflow\Domain\Make\StateTransition;
use AIC\Workflow\Exception\WorkflowRefusal;
use Closure;

use function array_map;
use function implode;
use function lcfirst;
use function str_replace;
use function ucwords;

/**
 * A state machine's files (`make:state`, #308): the backed enum of its
 * states, the class that declares its transitions, the migration that adds
 * its field, and the lines that put both on the entity type.
 */
final readonly class StateSource
{
    /**
     * @param MakeTemplates $makeTemplates The built-in templates (#301).
     */
    public function __construct(
        private MakeTemplates $makeTemplates = new MakeTemplates(),
    ) {
    }

    /**
     * @param string                              $namespace The module's namespace.
     * @param string                              $enum      The enum's class.
     * @param array{table: string, field: string} $place     Where the state lives.
     * @param list<string>                        $states    The states, the initial one first.
     *
     * @throws WorkflowRefusal When the template is missing.
     *
     * @return string The enum's bytes.
     */
    public function enum(string $namespace, string $enum, array $place, array $states): string
    {
        $cases = array_map(static fn (string $state): string => '    case ' . self::case($state) . " = '" . $state . "';", $states);

        return $this->makeTemplates->render('state', 'enum.php.tpl', [
            'cases' => implode("\n\n", $cases),
            'enum' => $enum,
            'field' => $place['field'],
            'namespace' => $namespace,
            'table' => $place['table'],
        ]);
    }

    /**
     * @param string                              $namespace   The module's namespace.
     * @param array{class: string, enum: string}  $names       The transitions class and the enum.
     * @param array{table: string, field: string} $place       Where the state lives.
     * @param list<string>                        $states      The states, the initial one first.
     * @param list<StateTransition>               $transitions The transitions.
     *
     * @throws WorkflowRefusal When the template is missing.
     *
     * @return string The transitions class's bytes.
     */
    public function machine(string $namespace, array $names, array $place, array $states, array $transitions): string
    {
        $enum = $names['enum'];
        $value = static fn (string $state): string => $enum . '::' . self::case($state) . '->value';
        $calls = '';
        $methods = '';

        foreach ($transitions as $transition) {
            $calls .= "\n            \$this->" . lcfirst(self::case($transition->name)) . '(),';
            $methods .= self::method($transition, $value);
        }

        return $this->makeTemplates->render('state', 'machine.php.tpl', [
            'calls' => $calls,
            'class' => $names['class'],
            'field' => $place['field'],
            'initial' => $states[0] ?? '',
            'methods' => $methods,
            'namespace' => $namespace,
            'states' => implode('', array_map(static fn (string $state): string => "\n            " . $value($state) . ',', $states)),
            'table' => $place['table'],
        ]);
    }

    /**
     * @param string                              $namespace The module's namespace.
     * @param string                              $class     The migration.
     * @param string                              $version   The version its ledger row records.
     * @param array{table: string, field: string} $place     Where the state lives.
     * @param list<string>                        $states    The states, the initial one first.
     *
     * @throws WorkflowRefusal When the template is missing.
     *
     * @return string The migration's bytes.
     */
    public function migration(string $namespace, string $class, string $version, array $place, array $states): string
    {
        return $this->makeTemplates->render('state', 'migration.php.tpl', [
            'choices' => "'" . implode("', '", $states) . "'",
            'class' => $class,
            'field' => $place['field'],
            'initial' => $states[0] ?? '',
            'namespace' => $namespace,
            'table' => $place['table'],
            'version' => $version,
        ]);
    }

    /**
     * @param string                              $class  The transitions class.
     * @param array{table: string, field: string} $place  Where the state lives.
     * @param list<string>                        $states The states.
     *
     * @return list<string> What to add by hand to the table's entity type, and how to take a transition.
     */
    public function lines(string $class, array $place, array $states): array
    {
        return [
            'Add to ' . $place['table'] . "'s entity type the field new Field('" . $place['field'] . "', FieldType::Choice, false, null, ['"
                . implode("', '", $states) . "']) and stateMachine: (new " . $class . '())->machine().',
            'Take a transition with EntityTransitions::apply(<type>, <key>, <transition>, <actor>); the field is never written by hand.',
        ];
    }

    /**
     * @param StateTransition         $stateTransition A transition.
     * @param Closure(string): string $value           A state's enum value, as code.
     *
     * @return string Its private method in the transitions class, after a blank line.
     */
    private static function method(StateTransition $stateTransition, Closure $value): string
    {
        $who = $stateTransition->permission === ''
            ? 'any actor'
            : 'an actor holding ' . $stateTransition->permission;

        return "\n\n    /**\n     * @throws InvalidArgumentException When the transition is declared wrongly.\n     *\n"
            . '     * @return Transition `' . $stateTransition->name . '`: from ' . implode(' or ', $stateTransition->from) . ' to '
            . $stateTransition->into . ', for ' . $who . ".\n     */\n    private function " . lcfirst(self::case($stateTransition->name))
            . "(): Transition\n    {\n        return new Transition(\n            '" . $stateTransition->name . "',\n            ["
            . implode(', ', array_map($value, $stateTransition->from)) . "],\n            " . $value($stateTransition->into)
            . ",\n            '" . $stateTransition->permission . "',\n        );\n    }";
    }

    /**
     * @param string $state A state, such as `on_hold`.
     *
     * @return string Its enum case, such as `OnHold`.
     */
    private static function case(string $state): string
    {
        return str_replace('_', '', ucwords($state, '_'));
    }
}
