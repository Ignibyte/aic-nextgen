<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\NativeSiteStep;
use InvalidArgumentException;
use stdClass;

use function array_is_list;
use function get_object_vars;
use function is_array;
use function is_bool;
use function is_int;
use function is_string;
use function json_decode;

/**
 * The requests gate:23 sends to both sites, in order (#130): the app's own
 * script, `config/native-site.json` (`aic.native-site/1`), one step a line so
 * an added step is an added line. JSON, not PHP, so `config/` names no
 * dev-only class. AIC's script holds its pages, forms, visits, notes, stock,
 * sign-in and Tessera steps; each site keeps its own session and token, and
 * deletes the note it made, so both see the same database.
 */
final readonly class NativeSiteScript
{
    /**
     * The script, relative to the project's root.
     */
    public const string PATH = 'config/native-site.json';

    /**
     * @param QuietFileReader $quietFileReader Reads the script.
     */
    public function __construct(
        private QuietFileReader $quietFileReader = new QuietFileReader(),
    ) {
    }

    /**
     * @param string $root The project's root.
     *
     * @throws InvalidArgumentException When the script is not an `aic.native-site/1` object, naming the step and field.
     *
     * @return list<NativeSiteStep> The steps, in order.
     */
    public function steps(string $root): array
    {
        $steps = get_object_vars($this->script($root))['steps'] ?? null;

        if (!is_array($steps) || $steps === [] || !array_is_list($steps)) {
            throw new InvalidArgumentException(self::PATH . ' holds no list of steps.');
        }

        $read = [];

        foreach ($steps as $index => $step) {
            $read[] = $this->step($index, $step instanceof stdClass ? $step : new stdClass());
        }

        return $read;
    }

    /**
     * @param string $root The project's root.
     *
     * @throws InvalidArgumentException When the script is not an `aic.native-site/1` object.
     *
     * @return stdClass The script, decoded.
     */
    private function script(string $root): stdClass
    {
        $script = json_decode($this->quietFileReader->read($root . '/' . self::PATH) ?? '');
        $schema = $script instanceof stdClass
            ? get_object_vars($script)['schema'] ?? null
            : null;

        return $script instanceof stdClass && $schema === 'aic.native-site/1'
            ? $script
            : throw new InvalidArgumentException(self::PATH . ' is not an aic.native-site/1 object.');
    }

    /**
     * @param int      $index Its place, from 0.
     * @param stdClass $step  Its fields.
     *
     * @throws InvalidArgumentException When a field is missing or of the wrong type.
     *
     * @return NativeSiteStep The step.
     */
    private function step(int $index, stdClass $step): NativeSiteStep
    {
        return new NativeSiteStep(
            $this->text($index, $step, 'name'),
            $this->text($index, $step, 'method'),
            $this->text($index, $step, 'path'),
            $this->text($index, $step, 'form'),
            $this->text($index, $step, 'header'),
            $this->number($index, $step),
            $this->flag($index, $step),
        );
    }

    /**
     * @param int      $index The step's place.
     * @param stdClass $step  Its fields.
     * @param string   $field A string field.
     *
     * @throws InvalidArgumentException When it is missing or not a string.
     *
     * @return string Its value.
     */
    private function text(int $index, stdClass $step, string $field): string
    {
        $value = get_object_vars($step)[$field] ?? null;

        return is_string($value)
            ? $value
            : throw $this->wrong($index, $field, 'string');
    }

    /**
     * @param int      $index The step's place.
     * @param stdClass $step  Its fields.
     *
     * @throws InvalidArgumentException When `status` is missing or not an integer.
     *
     * @return int Its value.
     */
    private function number(int $index, stdClass $step): int
    {
        $value = get_object_vars($step)['status'] ?? null;

        return is_int($value)
            ? $value
            : throw $this->wrong($index, 'status', 'integer');
    }

    /**
     * @param int      $index The step's place.
     * @param stdClass $step  Its fields.
     *
     * @throws InvalidArgumentException When `captures` is missing or not a boolean.
     *
     * @return bool Its value.
     */
    private function flag(int $index, stdClass $step): bool
    {
        $value = get_object_vars($step)['captures'] ?? null;

        return is_bool($value)
            ? $value
            : throw $this->wrong($index, 'captures', 'boolean');
    }

    /**
     * @param int    $index The step's place.
     * @param string $field The field.
     * @param string $type  What it must be.
     *
     * @return InvalidArgumentException The refusal, naming them.
     */
    private function wrong(int $index, string $field, string $type): InvalidArgumentException
    {
        return new InvalidArgumentException(self::PATH . ': step ' . $index . '\'s "' . $field . '" must be a ' . $type . '.');
    }
}
