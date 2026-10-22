<?php

declare(strict_types=1);

/*
 * The seeker probe's PHP (#103), run by seeker-checklist.sh in a scratch app:
 *
 * - `probe.php strip <package-copy>`: the app's composer.json requires
 *   aic/workflow by path from the copy, and the tool configs and gate:28 lose
 *   the package's own source, which the app does not hold.
 * - `probe.php agent <agent-file>`: the agent file as `--agents` JSON, so the
 *   seeker runs headless with no project settings and no hooks.
 * - `probe.php field <json-file> <key>`: one string field of a JSON object.
 * - `probe.php score <ledger-file> <item>`: the ledger read by the workflow's
 *   own parser; prints `held` (an open CRITICAL or HIGH finding citing the
 *   item; `*` for any item, as the clean control counts a false hold),
 *   `caught` (an open finding citing it), `missed`, or `invalid` when the
 *   text is no ledger.
 */

use AIC\Workflow\Application\SeekerLedgerParser;
use AIC\Workflow\Domain\SeekerStatus;
use AIC\Workflow\Exception\WorkflowRefusal;
use AIC\Workflow\Infrastructure\SeekerChecklistFile;

$verb = $argv[1] ?? '';

if ($verb === 'strip') {
    $package = $argv[2] ?? '';
    $configs = ['phpstan.neon', 'psalm.xml', 'phpcs.xml.dist', 'rector.php', 'deptrac.yaml', 'gates/src/Application/DeduplicatorGate.php', 'gates/src/Application/ProjectManifests.php'];

    foreach ($configs as $config) {
        $lines = explode("\n", (string) file_get_contents($config));
        $kept = array_filter($lines, static fn (string $line): bool => preg_match('#workflow/(src|bin|rules|composer\\.json)#', $line) !== 1);
        file_put_contents($config, implode("\n", $kept));
    }

    $composer = str_replace(['"url": "workflow"', ',workflow/src,workflow/rules'], ['"url": "' . $package . '/workflow"', ''], (string) file_get_contents('composer.json'));
    $lines = array_filter(explode("\n", $composer), static fn (string $line): bool => !str_contains($line, '"gate:28"'));
    file_put_contents('composer.json', implode("\n", $lines));

    exit(0);
}

if ($verb === 'agent') {
    $text = (string) file_get_contents($argv[2] ?? '');
    $parts = explode("---\n", $text, 3);
    $fields = [];

    foreach (explode("\n", $parts[1] ?? '') as $line) {
        $pair = explode(': ', $line, 2);

        if (count($pair) === 2) {
            $fields[$pair[0]] = $pair[1];
        }
    }

    $agent = [
        'description' => $fields['description'] ?? '',
        'model' => $fields['model'] ?? 'inherit',
        'prompt' => trim($parts[2] ?? ''),
        'tools' => array_map(trim(...), explode(',', $fields['tools'] ?? '')),
    ];
    echo json_encode([$fields['name'] ?? 'aic-seeker' => $agent], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);

    exit(0);
}

if ($verb === 'field') {
    $decoded = json_decode((string) file_get_contents($argv[2] ?? ''));
    $value = $decoded instanceof stdClass
        ? get_object_vars($decoded)[$argv[3] ?? ''] ?? ''
        : '';
    echo is_string($value)
        ? $value
        : '';

    exit(0);
}

require getcwd() . '/vendor/autoload.php';

$item = $argv[3] ?? '';
$checklist = new SeekerChecklistFile((string) getcwd(), getcwd() . '/vendor/aic/workflow', 'vendor');

try {
    $ledger = (new SeekerLedgerParser($checklist))->parse((string) file_get_contents($argv[2] ?? ''));
} catch (WorkflowRefusal) {
    echo 'invalid';

    exit(0);
}

$verdict = 'missed';

foreach ($ledger->findings as $finding) {
    if (($item !== '*' && $finding->item !== $item) || $finding->seekerStatus !== SeekerStatus::Open) {
        continue;
    }

    $verdict = $finding->seekerSeverity->holds()
        ? 'held'
        : ($verdict === 'held' ? 'held' : 'caught');
}

echo $verdict;
