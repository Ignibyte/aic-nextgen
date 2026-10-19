<?php

declare(strict_types=1);

namespace Gates\Application;

use JsonException;

use function array_key_exists;
use function file_get_contents;
use function fwrite;
use function is_array;
use function is_file;
use function is_readable;
use function is_string;
use function json_decode;
use function sprintf;

use const JSON_THROW_ON_ERROR;
use const PHP_EOL;

/**
 * Reads the enabled hooks from `captainhook.json` for the captainhook gate (gate:20).
 *
 * Split from CaptainhookGate, which keeps the checks on the installed hook
 * files and on `captainhook config:info`.
 */
final readonly class CaptainhookConfigReader
{
    /**
     * Locate `captainhook.json`, decode it, and return its enabled-hook list.
     *
     * Returns `null` when the file is missing or structurally invalid.
     *
     * @param string   $configPath Absolute path to `captainhook.json`.
     * @param resource $stderr     Stream parse-failure messages are written to.
     *
     * @throws JsonException When `captainhook.json` is not valid JSON.
     *
     * @return list<string>|null
     */
    public function enabledHooks(string $configPath, mixed $stderr): ?array
    {
        $raw = $this->readConfigFile($configPath, $stderr);

        if (!is_string($raw)) {
            return null;
        }

        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($decoded)) {
            fwrite($stderr, 'gate:20 — captainhook.json did not decode to an array.' . PHP_EOL);

            return null;
        }

        /** @var array<string, array{enabled?: bool}> $entries */
        $entries = $decoded;

        return $this->scanForEnabledHooks($entries);
    }

    /**
     * Read `$configPath` and return its contents; emit a contextual error and return `null` on failure.
     *
     * @param string   $configPath Absolute path to `captainhook.json`.
     * @param resource $stderr     Stream parse-failure messages are written to.
     */
    private function readConfigFile(string $configPath, mixed $stderr): ?string
    {
        if (!is_file($configPath)) {
            fwrite($stderr, sprintf('gate:20 — captainhook.json not found at %s.%s', $configPath, PHP_EOL));

            return null;
        }

        if (!is_readable($configPath)) {
            fwrite($stderr, sprintf('gate:20 — failed to read %s.%s', $configPath, PHP_EOL));

            return null;
        }

        return (string) file_get_contents($configPath);
    }

    /**
     * Filter the decoded `captainhook.json` to keys whose value is `enabled: true`.
     *
     * The top-level `config` block is removed before scanning, so a
     * pathological `{"config": {"enabled": true}}` test case cannot
     * pollute the enabled-hook list.
     *
     * @param array<string, array{enabled?: bool}> $decoded Decoded `captainhook.json`.
     *
     * @return list<string>
     */
    private function scanForEnabledHooks(array $decoded): array
    {
        unset($decoded['config']);
        $hooks = [];

        foreach ($decoded as $key => $value) {
            if (!$this->isEnabledHookEntry($value)) {
                continue;
            }

            $hooks[] = $key;
        }

        return $hooks;
    }

    /**
     * Decide whether `$value` represents an enabled hook entry.
     *
     * @param array{enabled?: bool} $value Decoded value at the hook key.
     */
    private function isEnabledHookEntry(array $value): bool
    {
        if (!array_key_exists('enabled', $value)) {
            return false;
        }

        return $value['enabled'];
    }
}
