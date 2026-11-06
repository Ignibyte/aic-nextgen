<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\SuiteGate;
use stdClass;

use function array_key_exists;
use function array_keys;
use function get_object_vars;
use function is_string;
use function json_encode;
use function str_starts_with;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * What composer.json carries for the suite (#130): the scripts as ignibyte/aic-gates
 * ships them (`build`, `gate`, `native:serve`, and one `gate:N` alias per gate
 * the project runs, no other script named `gate…`) and the four plugins the
 * tools need, allowed. gate:30 checks them; `aic-gate init` prints them,
 * since composer.json is the project's to write.
 */
final readonly class SuiteComposer
{
    /**
     * The Composer callback that lifts the process timeout for a long script, as composer.json writes it. It is built
     * from the namespace: the class is Composer's, and the gates name it in a script line, never in code (#279).
     */
    private const string NO_TIMEOUT = self::COMPOSER . '\\Config::disableProcessTimeout';

    /**
     * Composer's namespace, the start of the callback above.
     */
    private const string COMPOSER = 'Composer';

    /**
     * The plugins the tools install through.
     */
    private const array PLUGINS = [
        'captainhook/hook-installer',
        'dealerdirect/phpcodesniffer-composer-installer',
        'ergebnis/composer-normalize',
        'phpstan/extension-installer',
    ];

    /**
     * @param GateSuite $gateSuite The gates the project runs.
     */
    public function __construct(
        private GateSuite $gateSuite,
    ) {
    }

    /**
     * @param stdClass $composer The project's composer.json, decoded as objects.
     *
     * @return list<string> What differs from the shipped lines, each with the line to write.
     */
    public function findings(stdClass $composer): array
    {
        $scripts = $this->member($composer, 'scripts');
        $plugins = $this->member($this->member($composer, 'config'), 'allow-plugins');

        return [...$this->scriptFindings($scripts), ...$this->strayFindings($scripts), ...$this->pluginFindings($plugins)];
    }

    /**
     * @return string The lines composer.json carries, as JSON objects to paste.
     */
    public function lines(): string
    {
        $plugins = [];

        foreach (self::PLUGINS as $plugin) {
            $plugins[$plugin] = true;
        }

        return '"scripts": ' . $this->encode($this->scripts()) . ",\n\"config\": {\"allow-plugins\": " . $this->encode($plugins) . '}';
    }

    /**
     * @param stdClass $scripts composer.json's scripts.
     *
     * @return list<string> Each shipped script that is missing or differs.
     */
    private function scriptFindings(stdClass $scripts): array
    {
        $present = get_object_vars($scripts);
        $findings = [];

        foreach ($this->scripts() as $name => $command) {
            if (($present[$name] ?? null) === $command) {
                continue;
            }

            $findings[] = 'composer.json\'s script "' . $name . '" must be ' . $this->encode($command) . '.';
        }

        return $findings;
    }

    /**
     * @param stdClass $scripts composer.json's scripts.
     *
     * @return list<string> Each script named `gate…` that the suite does not ship.
     */
    private function strayFindings(stdClass $scripts): array
    {
        $expected = $this->scripts();
        $findings = [];

        foreach (array_keys(get_object_vars($scripts)) as $name) {
            if (!$this->gateScript($name) || array_key_exists($name, $expected)) {
                continue;
            }

            $findings[] = 'composer.json\'s script "' . $name . '" is not the suite\'s; remove it (aic-gate --list names the gates).';
        }

        return $findings;
    }

    /**
     * @param int|string $name A script key from decoded JSON.
     *
     * @return bool Whether its string name belongs to the gate namespace.
     */
    private function gateScript(int|string $name): bool
    {
        return is_string($name) && str_starts_with($name, 'gate');
    }

    /**
     * @param stdClass $plugins composer.json's allowed plugins.
     *
     * @return list<string> Each plugin the tools need that is not allowed.
     */
    private function pluginFindings(stdClass $plugins): array
    {
        $allowed = get_object_vars($plugins);
        $findings = [];

        foreach (self::PLUGINS as $plugin) {
            if (($allowed[$plugin] ?? null) === true) {
                continue;
            }

            $findings[] = 'composer.json\'s config.allow-plugins must allow "' . $plugin . '": true.';
        }

        return $findings;
    }

    /**
     * @param stdClass $object A decoded object.
     * @param string   $key    One of its keys.
     *
     * @return stdClass The object at the key; an empty one when there is none.
     */
    private function member(stdClass $object, string $key): stdClass
    {
        $fields = get_object_vars($object);

        return array_key_exists($key, $fields) && $fields[$key] instanceof stdClass
            ? $fields[$key]
            : new stdClass();
    }

    /**
     * @return array<string, string|list<string>> The suite's scripts, by name.
     */
    private function scripts(): array
    {
        $scripts = [
            'build' => 'aic-build',
            'gate' => [self::NO_TIMEOUT, 'aic-gate'],
        ];

        foreach ($this->gateSuite->gates() as $suiteGate) {
            $scripts[$suiteGate->name()] = $this->alias($suiteGate);
        }

        return [...$scripts, 'native:serve' => [self::NO_TIMEOUT, 'aic-gate serve']];
    }

    /**
     * @param SuiteGate $suiteGate A gate.
     *
     * @return string Its composer alias.
     */
    private function alias(SuiteGate $suiteGate): string
    {
        return 'aic-gate ' . $suiteGate->number;
    }

    /**
     * @param array<string, bool|list<string>|string>|list<string>|string $value A script, the scripts, or the plugins.
     *
     * @return string It as JSON.
     */
    private function encode(array|string $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
