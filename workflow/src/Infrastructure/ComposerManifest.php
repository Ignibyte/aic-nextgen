<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use JsonException;
use stdClass;

use function array_key_exists;
use function file_get_contents;
use function get_object_vars;
use function is_file;
use function is_string;
use function json_decode;
use function realpath;
use function rtrim;
use function str_contains;
use function str_starts_with;

use const JSON_THROW_ON_ERROR;

/**
 * The project's composer.json, read and never written (#105): whether it
 * requires `aic/workflow` for development only, and where composer puts the
 * packages.
 */
final readonly class ComposerManifest
{
    /**
     * The command that declares the workflow for development.
     */
    private const string REQUIRE_DEV = 'composer require --dev aic/workflow:0.1.x-dev';

    /**
     * @param string $root    The project's root.
     * @param string $package The workflow package's root, which must not lie inside the project.
     */
    public function __construct(
        private string $root,
        private string $package,
    ) {
    }

    /**
     * @return string What is wrong with its declaration of `aic/workflow`; empty when it is in `require-dev` alone.
     */
    public function problem(): string
    {
        $composer = $this->composer();

        return match (true) {
            $this->holdsSource() => "this project holds aic/workflow's own source; init installs into an app that requires the package.",
            !$composer instanceof stdClass => 'composer.json is missing or not JSON; init reads its require-dev.',
            $this->requires($composer, 'require') => 'composer.json requires aic/workflow at run time; it is a development tool: '
                . 'composer remove aic/workflow, then ' . self::REQUIRE_DEV . '.',
            !$this->requires($composer, 'require-dev') => 'composer.json does not require aic/workflow for development: run '
                . self::REQUIRE_DEV . '.',
            str_starts_with($this->vendorDir(), '/') || str_contains('/' . $this->vendorDir() . '/', '/../') => "composer.json's "
                . 'config.vendor-dir is outside the project; the hooks name the guard inside it.',
            default => '',
        };
    }

    /**
     * @return string Its `config.vendor-dir`, `vendor` when it sets none.
     */
    public function vendorDir(): string
    {
        $config = get_object_vars($this->composer() ?? new stdClass())['config'] ?? null;
        $vendorDir = $config instanceof stdClass
            ? get_object_vars($config)['vendor-dir'] ?? null
            : null;

        return is_string($vendorDir) && $vendorDir !== ''
            ? rtrim($vendorDir, '/')
            : 'vendor';
    }

    /**
     * @return bool Whether the package's source lies inside the project, as in the repository that develops it: where
     *              init refuses and the probe sweep runs (#119).
     */
    public function holdsSource(): bool
    {
        $root = realpath($this->root);
        $package = realpath($this->package);

        return is_string($root) && is_string($package) && str_starts_with($package . '/', $root . '/');
    }

    /**
     * @param stdClass $composer composer.json.
     * @param string   $section  `require` or `require-dev`.
     *
     * @return bool Whether the section names `aic/workflow`.
     */
    private function requires(stdClass $composer, string $section): bool
    {
        $packages = get_object_vars($composer)[$section] ?? null;

        return $packages instanceof stdClass && array_key_exists('aic/workflow', get_object_vars($packages));
    }

    /**
     * @return stdClass|null composer.json; null when it is missing or not a JSON object.
     */
    private function composer(): ?stdClass
    {
        $path = $this->root . '/composer.json';
        $text = is_file($path)
            ? file_get_contents($path)
            : false;

        try {
            $composer = is_string($text)
                ? json_decode($text, false, 64, JSON_THROW_ON_ERROR)
                : null;
        } catch (JsonException) {
            return null;
        }

        return $composer instanceof stdClass
            ? $composer
            : null;
    }
}
