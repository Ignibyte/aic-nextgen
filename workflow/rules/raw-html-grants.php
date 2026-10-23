<?php

declare(strict_types=1);

/*
 * House rule raw-html-grants (#104): a run adds no RawHtml grant and raises
 * none: grants are the operator's.
 *
 * It reads its aic.house-rule/1 input on stdin and, when config/raw-html.php
 * changed, loads both versions through the project's autoloader and compares
 * the grants by class: a new class, a higher count or a reworded reason
 * breaks; a grant removed or lowered holds. Exit 0 holds, 1 breaks (each
 * reason a line), 4 when the file did not change, 3 when the input or a
 * version cannot be read.
 */

use AIC\Component\Contracts\RawHtmlGrant;

$input = json_decode((string) file_get_contents('php://stdin'));

$fields = $input instanceof stdClass
    ? get_object_vars($input)
    : [];
$changes = [$fields['changed'] ?? null];
$autoload = [$fields['autoload'] ?? null];

if (!$input instanceof stdClass || !is_array($changes[0]) || !is_string($autoload[0])) {
    file_put_contents('php://stderr', "raw-html-grants: the input is not an aic.house-rule/1 object.\n");

    exit(3);
}

$change = null;

foreach (array_keys($changes[0]) as $changeAt) {
    $candidate = [$changes[0][$changeAt]];
    $change = $candidate[0] instanceof stdClass && ($candidate[0]->path ?? null) === 'config/raw-html.php'
        ? $candidate[0]
        : $change;
}

if (!$change instanceof stdClass) {
    echo "config/raw-html.php did not change.\n";

    exit(4);
}

$autoloadPath = $autoload[0];

if (!is_file($autoloadPath) || !is_readable($autoloadPath)) {
    file_put_contents('php://stderr', "raw-html-grants: the project autoloader cannot be read.\n");

    exit(3);
}

require $autoloadPath;

$index = static function (array $grants): ?array {
    $byClass = [];

    foreach ($grants as $grant) {
        if (!$grant instanceof RawHtmlGrant) {
            return null;
        }

        $byClass[$grant->class] = $grant;
    }

    return $byClass;
};
$load = static function (?string $file) use ($index): ?array {
    if ($file === null) {
        return [];
    }

    if (!is_file($file) || !is_readable($file)) {
        return null;
    }

    $grants = [require $file];

    return is_array($grants[0])
        ? $index($grants[0])
        : null;
};
$base = [get_object_vars($change)['base'] ?? null];
$head = [get_object_vars($change)['head'] ?? null];
$before = $load(is_string($base[0]) ? $base[0] : null);
$after = $load(is_string($head[0]) ? $head[0] : null);

if ($before === null || $after === null) {
    echo "a version of config/raw-html.php does not return a list of RawHtmlGrant.\n";

    exit(3);
}

$breaks = [];

foreach ($after as $class => $grant) {
    $was = $before[$class] ?? null;
    $breaks = match (true) {
        !$was instanceof RawHtmlGrant => [...$breaks, 'a RawHtml grant for ' . $class . ' was added.'],
        $grant->uses > $was->uses => [
            ...$breaks,
            'the RawHtml grant for ' . $class . ' was raised from ' . $was->uses . ' to ' . $grant->uses . '.',
        ],
        $grant->because !== $was->because => [...$breaks, 'the RawHtml grant for ' . $class . ' was given another reason.'],
        default => $breaks,
    };
}

echo $breaks === []
    ? "no RawHtml grant was added or raised.\n"
    : implode("\n", $breaks) . "\n";

exit($breaks === [] ? 0 : 1);
