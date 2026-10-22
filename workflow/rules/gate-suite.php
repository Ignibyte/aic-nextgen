<?php

declare(strict_types=1);

/*
 * House rule gate-suite (#104): a run leaves the gate suite as it began, but
 * for the lines a new module needs.
 *
 * It reads its aic.house-rule/1 input on stdin and judges the changed paths
 * that configure or implement a gate. Any change under gates/ (aic/gates in
 * this repo: the runner, the entries, the build, the code and the base configs,
 * #116), to a gate entry at the root, or to a tool's configuration breaks; in
 * phpcs.xml.dist only an added `<element key="modules/<m>/src" value="App\<M>"/>`
 * holds; in composer.json only the keys the suite owns are compared (the
 * scripts, captainhook's settings, the allowed plugins, the aic/gates
 * constraint), so a module's PSR-4 line and a dependency stay free (gates 0 to
 * 4 judge those). Exit 0 holds, 1 breaks (each reason a line), 4 when no such
 * path changed, 3 when the input cannot be read.
 */

$input = json_decode((string) file_get_contents('php://stdin'));

if (!$input instanceof stdClass || !is_array($input->changed ?? null)) {
    file_put_contents('php://stderr', "gate-suite: the input is not an aic.house-rule/1 object.\n");

    exit(3);
}

$fixed = [
    'phpstan.neon', 'phpstan-architecture.neon', 'psalm.xml', 'deptrac.yaml', 'pint.json', 'rector.php', 'captainhook.json',
];
// composer.json's keys the suite owns, each a path into the decoded object (#116): its scripts, captainhook's
// settings, the plugins it allows, and the aic/gates constraint.
$owned = [
    ['scripts'], ['scripts-descriptions'], ['extra', 'captainhook'], ['config', 'allow-plugins'], ['require-dev', 'aic/gates'],
];
$at = static fn (mixed $value, string $key): mixed => is_array($value)
    ? $value[$key] ?? null
    : null;
$read = static fn (mixed $file): string => is_string($file) && is_file($file)
    ? (string) file_get_contents($file)
    : '';
$lines = static fn (string $text): array => array_count_values(array_map(trim(...), explode("\n", $text)));
$breaks = [];
$examined = 0;

foreach ($input->changed as $change) {
    $path = $change instanceof stdClass && is_string($change->path ?? null)
        ? $change->path
        : '';
    $base = $change instanceof stdClass
        ? $read($change->base ?? null)
        : '';
    $head = $change instanceof stdClass
        ? $read($change->head ?? null)
        : '';

    if (in_array($path, $fixed, true) || str_starts_with($path, 'bin/gate-') || str_starts_with($path, 'gates/')) {
        $examined += 1;
        $breaks[] = $path . ' configures or implements a gate, and the run changed it.';

        continue;
    }

    if ($path === 'phpcs.xml.dist') {
        $examined += 1;
        $before = $lines($base);
        $after = $lines($head);

        foreach ($before as $line => $count) {
            if (!(($after[$line] ?? 0) < $count)) {
                continue;
            }

            $breaks[] = 'phpcs.xml.dist lost the line "' . $line . '".';
        }

        foreach ($after as $line => $count) {
            $added = ($before[$line] ?? 0) < $count;

            if (
                !$added
                || preg_match('#^<element key="modules/[a-z0-9_-]+/src" value="App\\\\[A-Za-z0-9]+"/>$#', $line) === 1
            ) {
                continue;
            }

            $breaks[] = 'phpcs.xml.dist gained "' . $line . '"; only a module\'s root may be added.';
        }

        continue;
    }

    if ($path !== 'composer.json') {
        continue;
    }

    $examined += 1;
    $before = json_decode($base, true);
    $after = json_decode($head, true);

    foreach ($owned as $keys) {
        $was = $before;
        $now = $after;

        foreach ($keys as $key) {
            $was = $at($was, $key);
            $now = $at($now, $key);
        }

        if ($was === $now) {
            continue;
        }

        $breaks[] = "composer.json's " . implode('.', $keys) . " changed: the gate suite is not the run's to change.";
    }
}

if ($examined === 0) {
    echo "no path that configures or implements a gate changed.\n";

    exit(4);
}

echo $breaks === []
    ? "the gate suite is as it began.\n"
    : implode("\n", $breaks) . "\n";

exit($breaks === [] ? 0 : 1);
