<?php

declare(strict_types=1);

/*
 * House rule scaffold-only (#302), opt-in: every class and template a run
 * adds under modules/ or themes/ came from a scaffold, so it has a row in
 * the made ledger, .aic/made.jsonl. The engine's made row proves each row
 * was written by a make; this rule asks that every new .php and .phtml file
 * there has one. Edits to a made file stay free.
 *
 * It reads its aic.house-rule/1 input on stdin. Exit 0 holds, 1 breaks
 * (each file a line), 4 when the run added no such file, 3 when the input
 * or the ledger cannot be read.
 */

$input = json_decode((string) file_get_contents('php://stdin'));

$fields = $input instanceof stdClass
    ? get_object_vars($input)
    : [];
$changes = [$fields['changed'] ?? null];

if (!$input instanceof stdClass || !is_array($changes[0])) {
    file_put_contents('php://stderr', "scaffold-only: the input is not an aic.house-rule/1 object.\n");

    exit(3);
}

$added = [];
$ledger = null;

foreach (array_keys($changes[0]) as $changeAt) {
    $change = [$changes[0][$changeAt]];
    $parts = $change[0] instanceof stdClass
        ? get_object_vars($change[0])
        : [];
    $path = [$parts['path'] ?? null];
    $head = [$parts['head'] ?? null];
    $base = [$parts['base'] ?? null];

    if (!is_string($path[0]) || !is_string($head[0])) {
        continue;
    }

    $ledger = $path[0] === '.aic/made.jsonl'
        ? $head[0]
        : $ledger;
    $judged = $base[0] === null && preg_match('#^(?:modules|themes)/.+\\.(?:php|phtml)$#', $path[0]) === 1;
    $added = $judged
        ? [...$added, $path[0]]
        : $added;
}

if ($added === []) {
    echo "the run added no class or template under modules/ or themes/.\n";

    exit(4);
}

$text = is_string($ledger)
    ? file_get_contents($ledger)
    : '';

if ($text === false) {
    file_put_contents('php://stderr', "scaffold-only: the made ledger cannot be read.\n");

    exit(3);
}

$made = [];

foreach (explode("\n", $text) as $line) {
    $row = [json_decode($line)];
    $made = $row[0] instanceof stdClass && is_string($row[0]->path ?? null)
        ? [...$made, $row[0]->path]
        : $made;
}

$breaks = [];

foreach ($added as $path) {
    $breaks = in_array($path, $made, true)
        ? $breaks
        : [...$breaks, $path . ' was added by hand: make it with a kind bin/aic make:catalog lists, then edit it.'];
}

echo $breaks === []
    ? "every class and template the run added was made by a scaffold.\n"
    : implode("\n", $breaks) . "\n";

exit($breaks === [] ? 0 : 1);
