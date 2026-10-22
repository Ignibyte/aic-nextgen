<?php

declare(strict_types=1);

/*
 * gate:28 (#104): every house rule the workflow ships bites on its own probe.
 * Each rule in workflow/rules/ runs on each of its .probe/ cases: a held case
 * must exit 0, a broken case 1, and a rule with no held or no broken case
 * fails. The cases are laid out in a temporary directory, removed after.
 */

use AIC\Workflow\Application\RuleInput;
use AIC\Workflow\Application\RuleProbes;
use AIC\Workflow\Domain\HouseRule;
use AIC\Workflow\Infrastructure\LocalProcess;
use AIC\Workflow\Infrastructure\PhpRuleExecutor;
use AIC\Workflow\Infrastructure\ProbeFixtures;

require_once __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);
$work = sys_get_temp_dir() . '/aic-gate-house-rules-' . getmypid();
$ruleProbes = new RuleProbes(
    new ProbeFixtures($root, $work),
    new PhpRuleExecutor($root, new LocalProcess($root)),
    new RuleInput($root, $root . '/vendor/autoload.php'),
);
$rules = glob($root . '/workflow/rules/*.php');
$failures = [];

foreach ($rules === false ? [] : $rules as $file) {
    $houseRule = new HouseRule(basename($file, '.php'), '', 'workflow/rules/' . basename($file));
    $failures = [...$failures, ...$ruleProbes->failures($houseRule)];
}

foreach ($failures as $failure) {
    echo 'gate:28 — ', $failure, "\n";
}

exec('rm -rf ' . escapeshellarg($work));

echo 'gate:28 — house rules: ', count($rules === false ? [] : $rules), ' rule(s) probed, ', count($failures), " failure(s).\n";

exit($failures === [] ? 0 : 1);
