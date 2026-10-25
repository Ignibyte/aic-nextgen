<?php

declare(strict_types=1);

/*
 * 01-declaration (#119; #101 AC 2, 3, 6; #104): a declared file asset rebuilds
 * without declaring its generated outputs, which direct writes cannot edit.
 * At code an undeclared file, including an inventory filename suffix,
 * a new RawHtml grant and an edited rule file hold the run, and it advances
 * once they are restored; at check, a raised PHPMD limit passes gate:10 but
 * breaks the gate-suite rule, the declared changelog edit makes the gate run
 * again, and once the limit is restored the rule examines nothing
 * (deferred); at complete, a reworded criterion breaks the freeze, and once
 * it is restored and the criteria are ticked, the run completes.
 */

use AIC\Workflow\Domain\Probe\CommandStep;
use AIC\Workflow\Domain\Probe\DeleteStep;
use AIC\Workflow\Domain\Probe\Expect;
use AIC\Workflow\Domain\Probe\GuardStep;
use AIC\Workflow\Domain\Probe\ProbePlan;
use AIC\Workflow\Domain\Probe\ProbeScenario;
use AIC\Workflow\Domain\Probe\ReplaceStep;
use AIC\Workflow\Domain\Probe\RunStep;
use AIC\Workflow\Domain\Probe\WriteStep;

$probePlan = new ProbePlan();
$plan = $probePlan->toCode();
$write = static fn (string $path): string => '{"session_id":"probe","cwd":"{root}","tool_name":"Write","tool_input":'
    . '{"file_path":"{root}/' . $path . '","content":"probe"}}';
$shell = static fn (string $command): string => '{"session_id":"probe","cwd":"{root}","tool_name":"Bash","tool_input":'
    . '{"command":"' . $command . '"}}';
$verifyAsset = <<<'PHP'
require 'vendor/autoload.php';
$source = file_get_contents('modules/site/assets/workflow-probe.txt');
$published = file_get_contents('public/assets/site/workflow-probe.txt');
$inventory = json_decode(file_get_contents('build/assets.json'), true, 512, JSON_THROW_ON_ERROR);
$rows = array_values(array_filter($inventory['assets'], static fn (array $row): bool => $row['name'] === 'site/workflow-probe'));
$assets = new App\Generated\Assets();
$asset = $assets->named('site/workflow-probe');
$canonical = $asset === null ? false : file_get_contents('public/assets/' . $asset->file);
$ok = $source !== false && $source === $published && $source === $canonical && $inventory['schema'] === 'aic.assets/1'
    && count($rows) === 1 && $rows[0]['aliasPath'] === '/assets/site/workflow-probe.txt'
    && preg_match('#^/assets/[a-f0-9]{64}/site/workflow-probe[.]txt$#D', $rows[0]['path']) === 1
    && $rows[0]['mediaType'] === 'text/plain; charset=utf-8'
    && $rows[0]['sha256'] === hash('sha256', $source) && $rows[0]['size'] === strlen($source)
    && $asset !== null && $asset->file === $rows[0]['file'] && $asset->mediaType === $rows[0]['mediaType']
    && $asset->path === $rows[0]['path'] && $asset->aliasPath === $rows[0]['aliasPath']
    && $asset->sha256 === $rows[0]['sha256'] && $asset->size === $rows[0]['size']
    && $assets->byPath('/assets/site/workflow-probe.txt') === $asset && $assets->byPath($asset->path) === $asset;
fwrite($ok ? STDOUT : STDERR, $ok ? "asset publication and catalog match\n" : "asset publication or catalog differs\n");
exit($ok ? 0 : 1);
PHP;
$grants = new ReplaceStep(
    'config/raw-html.php',
    '$grants = [];',
    "\$grants = [new RawHtmlGrant('App\\\\Note\\\\Service\\\\NoteBadge', 1, 'probe')];",
);
$rule = new ReplaceStep(
    'vendor/aic/workflow/rules/gate-suite.php',
    'gate-suite: the input is not an aic.house-rule/1 object.',
    'gate-suite: the input is not an aic.house-rule/1 object (probe).',
);
$limit = new ReplaceStep(
    'gates/config/phpmd.xml',
    '<property name="minimum" value="31"/>',
    '<property name="minimum" value="41"/>',
);
$criterion = new ReplaceStep(
    'var/workflow/spec-1.md',
    '| 2 | The changelog names the fix | [ ] |',
    '| 2 | The changelog names the change | [ ] |',
);

return new ProbeScenario([
    ...array_slice($plan, 0, 2),
    new ReplaceStep(
        'var/workflow/spec-1.md',
        '| `CHANGELOG.md` | modify | The fix, named |',
        "| `CHANGELOG.md` | modify | The fix, named |\n"
            . "| `modules/site/site.module.php` | modify | Declare the public probe file |\n"
            . '| `modules/site/assets/workflow-probe.txt` | create | The declared file asset source |',
    ),
    new ReplaceStep(
        'var/workflow/spec-1.md',
        "1. Only the label's text changes.",
        "1. The label's text changes and a declared site file asset is rebuilt; no routes change.",
    ),
    ...array_slice($plan, 2),
    $probePlan->label(),
    new GuardStep('the builder command', 'shell', $shell('composer run build'), 0),
    new GuardStep(
        'a direct asset inventory write',
        'edit',
        $write('build/assets.json'),
        2,
        "build/assets.json is the build's: composer run build writes it.",
    ),
    new GuardStep(
        'a direct generated asset lookup write',
        'edit',
        $write('generated/Assets.php'),
        2,
        "generated/Assets.php is the build's: composer run build writes it.",
    ),
    new GuardStep(
        'a direct generated presentation write',
        'edit',
        $write('generated/Presentation.php'),
        2,
        "generated/Presentation.php is the build's: composer run build writes it.",
    ),
    new GuardStep(
        'a direct asset inventory shell write',
        'shell',
        $shell('printf probe > build/assets.json'),
        2,
        "build/assets.json is the build's: composer run build writes it.",
    ),
    new ReplaceStep(
        'modules/site/site.module.php',
        "    ->with(new ModuleAsset('styles', 'assets/site.css', ModuleAssetKind::Style))\n",
        "    ->with(new ModuleAsset('styles', 'assets/site.css', ModuleAssetKind::Style))\n"
            . "    ->with(new ModuleAsset('workflow-probe', 'assets/workflow-probe.txt', ModuleAssetKind::File))\n",
    ),
    new WriteStep('modules/site/assets/workflow-probe.txt', 'stray.txt.fixture'),
    new CommandStep(['composer', 'run', 'build']),
    new CommandStep(['php', '-r', $verifyAsset]),
    new RunStep('inspection-due', [
        new Expect('declaration', 'passed'),
        new Expect('gate', 'passed'),
        new Expect('seeker', 'due'),
    ]),
    ...array_slice($probePlan->inspected(), 1),
    new WriteStep('stray.txt', 'stray.txt.fixture'),
    new WriteStep('build/assets.json.unrelated', 'stray.txt.fixture'),
    $grants,
    $rule,
    new RunStep('blocked', [
        new Expect('declaration', 'failed', 'stray.txt'),
        new Expect('declaration', 'failed', 'build/assets.json.unrelated'),
        new Expect('rule:raw-html-grants', 'failed'),
        new Expect('rules', 'failed', 'gate-suite.php'),
    ]),
    new DeleteStep('stray.txt'),
    new DeleteStep('build/assets.json.unrelated'),
    new ReplaceStep($grants->path, $grants->into, $grants->from),
    new ReplaceStep($rule->path, $rule->into, $rule->from),
    new RunStep('advanced', [new Expect('declaration', 'passed'), new Expect('rules', 'passed'), new Expect('gate', 'passed')]),
    new ReplaceStep(
        'CHANGELOG.md',
        "## Unreleased\n",
        "## Unreleased\n\n- The home page's uploads link says each file is checked by its content.\n",
    ),
    $limit,
    new RunStep('blocked', [
        new Expect('gate', 'passed', 'the last gate verdict is stale (the tree changed), so the gate ran again'),
        new Expect('rule:gate-suite', 'failed', 'phpmd.xml'),
    ]),
    new ReplaceStep($limit->path, $limit->into, $limit->from),
    new RunStep('advanced', [
        new Expect('gate', 'passed', 'the last gate verdict is stale (the tree changed), so the gate ran again'),
        new Expect('rule:gate-suite', 'deferred', 'examined nothing'),
    ]),
    $criterion,
    new RunStep('blocked', [new Expect('freeze', 'failed', 'The changelog names the fix')]),
    new ReplaceStep($criterion->path, $criterion->into, $criterion->from),
    new ReplaceStep(
        'var/workflow/spec-1.md',
        "content | [ ] |\n| 2 | The changelog names the fix | [ ] |",
        "content | [x] gate:6 |\n| 2 | The changelog names the fix | [x] gate:6 |",
    ),
    new RunStep('completed', [new Expect('freeze', 'passed'), new Expect('criteria', 'passed')]),
], database: true);
