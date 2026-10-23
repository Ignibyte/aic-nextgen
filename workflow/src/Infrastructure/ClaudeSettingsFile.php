<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\HookWiring;
use AIC\Workflow\Application\PackSource;
use AIC\Workflow\Application\ProjectFiles;
use AIC\Workflow\Domain\InstallAction;
use AIC\Workflow\Domain\InstallLine;
use AIC\Workflow\Domain\RegexCaptures;
use AIC\Workflow\Exception\WorkflowRefusal;
use JsonException;
use Override;
use stdClass;

use function array_key_exists;
use function array_values;
use function get_object_vars;
use function implode;
use function intdiv;
use function is_array;
use function json_decode;
use function json_encode;
use function preg_replace_callback;
use function str_repeat;
use function strlen;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * `.claude/settings.json` (#105): the guard's four hooks from the package's
 * `hooks.json` merged in, and its `env` given the Stop cap, so the guard's
 * Stop hold is not cut off after Claude Code's default of nine blocks. The
 * content is kept; when something changed, the file is written again in its
 * own indent, so its layout may change. Nothing changed, nothing written.
 */
final readonly class ClaudeSettingsFile implements HookWiring
{
    use RegexCaptures;

    /**
     * The variable that raises Claude Code's cap on consecutive Stop-hook blocks.
     */
    public const string CAP = 'CLAUDE_CODE_STOP_HOOK_BLOCK_CAP';

    /**
     * @param ProjectFiles $projectFiles The project's files.
     * @param PackSource   $packSource   The package's `hooks.json`.
     * @param PackManifest $packManifest Where it is.
     * @param HookMerge    $hookMerge    The merge.
     */
    public function __construct(
        private ProjectFiles $projectFiles,
        private PackSource $packSource,
        private PackManifest $packManifest = new PackManifest(),
        private HookMerge $hookMerge = new HookMerge(),
    ) {
    }

    /**
     * @throws WorkflowRefusal When the file cannot take a regular file, is not a JSON object, or its `hooks` or `env` is
     *                         not one; or the package's `hooks.json` is broken.
     */
    #[Override]
    public function check(): void
    {
        $obstacle = $this->projectFiles->obstacle(PackManifest::SETTINGS);

        if ($obstacle !== '') {
            throw new WorkflowRefusal($obstacle . '; init writes only regular files, so move it aside first.');
        }

        $this->events($this->settings() ?? new stdClass());
        $this->shipped();
    }

    /**
     * @throws WorkflowRefusal When the file cannot be written.
     *
     * @return list<InstallLine> The hooks' line and the Stop cap's.
     */
    #[Override]
    public function wire(): array
    {
        $text = $this->projectFiles->read(PackManifest::SETTINGS);
        $settings = $this->settings() ?? new stdClass();
        $shipped = $this->shipped();
        $before = json_encode($settings, JSON_THROW_ON_ERROR);
        $tally = $this->hookMerge->merge($settings, $shipped);
        $installLine = $this->cap($settings, $shipped);
        $changed = $text === null || $before !== json_encode($settings, JSON_THROW_ON_ERROR);

        if ($changed) {
            $this->projectFiles->write(PackManifest::SETTINGS, $this->encode($settings, $text));
        }

        $why = "the guard's hooks: " . $tally['added'] . ' added, ' . $tally['upgraded'] . ' upgraded'
            . ($text === null ? '' : '; the content is kept, the layout re-indented');

        return [
            $tally['added'] + $tally['upgraded'] === 0
                ? new InstallLine(InstallAction::Current, PackManifest::SETTINGS, "the guard's hooks are wired")
                : new InstallLine(InstallAction::Wrote, PackManifest::SETTINGS, $why),
            $installLine,
        ];
    }

    /**
     * @param stdClass $settings The settings; their `env` gets the cap when it has none.
     * @param stdClass $shipped  The package's `hooks.json`.
     *
     * @return InstallLine Wrote when the cap was set, current when it is the shipped one, kept when the operator set
     *                     another.
     */
    private function cap(stdClass $settings, stdClass $shipped): InstallLine
    {
        $env = $this->hookMerge->object($settings, 'env');
        $value = [get_object_vars($env)[self::CAP] ?? null];
        $shippedValue = [get_object_vars($this->hookMerge->object($shipped, 'env'))[self::CAP] ?? null];
        $path = PackManifest::SETTINGS . ' env.' . self::CAP;

        if ($value[0] === null) {
            $env->{self::CAP} = $shippedValue[0];
            $settings->env = $env;

            return new InstallLine(InstallAction::Wrote, $path, 'the Stop hold is not cut off after nine blocks');
        }

        return $value[0] === $shippedValue[0]
            ? new InstallLine(InstallAction::Current, $path)
            : new InstallLine(InstallAction::Kept, $path, "the operator's value, " . json_encode($value[0], JSON_THROW_ON_ERROR));
    }

    /**
     * @param stdClass    $settings The settings.
     * @param string|null $text     The file's text before; null when it was new.
     *
     * @return string The settings as JSON in the file's indent unit (two spaces for a new file), with a final line break.
     */
    private function encode(stdClass $settings, ?string $text): string
    {
        $match = $text === null
            ? []
            : self::regexMatch('/^([ \\t]+)\\S/m', $text);
        $unit = array_key_exists(1, $match)
            ? $match[1]
            : '  ';
        $json = json_encode($settings, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $indented = preg_replace_callback(
            '/^(?: {4})+/m',
            static fn (array $spaces): string => str_repeat($unit, intdiv(strlen(implode('', $spaces)), 4)),
            $json,
        );

        return ($indented ?? $json) . "\n";
    }

    /**
     * @param stdClass $settings The settings.
     *
     * @throws WorkflowRefusal When its `hooks` or `env` is there and is not an object, or an event is not a list of objects.
     */
    private function events(stdClass $settings): void
    {
        $members = get_object_vars($settings);

        foreach (['hooks', 'env'] as $key) {
            if (array_key_exists($key, $members) && $members[$key] !== null && !$members[$key] instanceof stdClass) {
                throw new WorkflowRefusal(PackManifest::SETTINGS . '\'s "' . $key . '" is not an object; mend it by hand, then run init.');
            }
        }

        $this->lists($this->hookMerge->object($settings, 'hooks'));
    }

    /**
     * @param stdClass $hooks The settings' hooks.
     *
     * @throws WorkflowRefusal When an event is not a list of objects.
     */
    private function lists(stdClass $hooks): void
    {
        foreach ((new JsonReader($hooks, 'hooks'))->keys() as $event) {
            $list = [get_object_vars($hooks)[$event]];

            if (!is_array($list[0]) || $this->hookMerge->list($hooks, $event) !== array_values($list[0])) {
                throw new WorkflowRefusal(PackManifest::SETTINGS . "'s hooks." . $event . ' is not a list of objects; mend it by hand.');
            }
        }
    }

    /**
     * @throws WorkflowRefusal When it is there and is not a JSON object.
     *
     * @return stdClass|null The project's settings; null when there is no file.
     */
    private function settings(): ?stdClass
    {
        $text = $this->projectFiles->read(PackManifest::SETTINGS);

        return $text === null
            ? null
            : $this->decode($text, PackManifest::SETTINGS . ' is not a JSON object; mend it by hand, then run init.');
    }

    /**
     * @throws WorkflowRefusal When the package lacks it or it is broken.
     *
     * @return stdClass The package's `hooks.json`, `{vendor}` set.
     */
    private function shipped(): stdClass
    {
        $broken = "the package's pack/" . PackManifest::HOOKS . ' is missing or broken; reinstall aic/workflow.';

        return $this->decode($this->packSource->bytes($this->packManifest->hooks()) ?? throw new WorkflowRefusal($broken), $broken);
    }

    /**
     * @param string $text   JSON.
     * @param string $refuse What to say when it is not an object.
     *
     * @throws WorkflowRefusal When it is not a JSON object.
     *
     * @return stdClass The object.
     */
    private function decode(string $text, string $refuse): stdClass
    {
        try {
            $object = [json_decode($text, false, 64, JSON_THROW_ON_ERROR)];
        } catch (JsonException $jsonException) {
            throw new WorkflowRefusal($refuse, 0, $jsonException);
        }

        return $object[0] instanceof stdClass
            ? $object[0]
            : throw new WorkflowRefusal($refuse);
    }
}
