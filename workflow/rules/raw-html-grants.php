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

if (!$input instanceof stdClass || !is_array($input->changed ?? null) || !is_string($input->autoload ?? null)) {
    file_put_contents('php://stderr', "raw-html-grants: the input is not an aic.house-rule/1 object.\n");

    exit(3);
}

$change = null;

foreach ($input->changed as $candidate) {
    $change = $candidate instanceof stdClass && ($candidate->path ?? null) === 'config/raw-html.php'
        ? $candidate
        : $change;
}

if (!$change instanceof stdClass) {
    echo "config/raw-html.php did not change.\n";

    exit(4);
}

require $input->autoload;

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
    $grants = $file !== null && is_file($file)
        ? require $file
        : [];

    return is_array($grants)
        ? $index($grants)
        : null;
};
$before = $load(is_string($change->base ?? null) ? $change->base : null);
$after = $load(is_string($change->head ?? null) ? $change->head : null);

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
