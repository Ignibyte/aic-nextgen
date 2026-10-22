<?php

declare(strict_types=1);

/*
 * The seeker probe's PHP (#103, #127), run by seeker-checklist.sh in a scratch app made
 * by scratch-app.sh (#119):
 *
 * - `probe.php agent <agent-file>`: the agent file as `--agents` JSON, so the
 *   seeker runs headless with no project settings and no hooks.
 * - `probe.php field <json-file> <key>`: one string field of a JSON object.
 * - `probe.php score <ledger-file> <item>`: the ledger read by the workflow's
 *   own parser; prints `high` (an open CRITICAL or HIGH finding citing the
 *   item; `*` for any item, as the clean control counts a false alarm),
 *   `caught` (an open finding citing it), `missed`, or `invalid` when the
 *   text is no ledger.
 */

use AIC\Workflow\Application\SeekerLedgerParser;
use AIC\Workflow\Domain\SeekerStatus;
use AIC\Workflow\Exception\WorkflowRefusal;
use AIC\Workflow\Infrastructure\SeekerChecklistFile;

$verb = $argv[1] ?? '';

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
        ? 'high'
        : ($verdict === 'high' ? 'high' : 'caught');
}

echo $verdict;
