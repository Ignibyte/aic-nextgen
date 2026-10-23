<?php

declare(strict_types=1);

/*
 * The app builder's PHP (#119), run by scratch-app.sh in a new app:
 *
 * - `scratch-app.php strip <package-copy>`: the app's composer.json requires
 *   aic/workflow by path from the copy; PHPStan and Psalm read the package and the
 *   sweep's scenarios from the copy (the framework methods only it calls, and
 *   the step values only the scenarios build, stay used); the other tool
 *   configs, and the AIC roots in aic/gates' ProjectProfile (#130), lose the
 *   package's own source and probes, which the app does not hold; and gate:28
 *   and gate:29 go from the suite and their aliases from composer.json, as
 *   they hold AIC's own rules and docs.
 */

if (($argv[1] ?? '') !== 'strip' || ($argv[2] ?? '') === '') {
    fwrite(\STDERR, "usage: scratch-app.php strip <package-copy>\n");

    exit(2);
}

$package = $argv[2];
$own = '#workflow/(src|bin|rules|probes|composer\\.json)#';

$neon = (string) file_get_contents('phpstan.neon');
$neon = (string) preg_replace('#^(\\s*- )workflow/(src|bin|rules|probes/sweep)$#m', '$1' . $package . '/workflow/$2', $neon);
file_put_contents('phpstan.neon', $neon);

$xmlPackage = htmlspecialchars($package);
$psalm = str_replace('name="workflow/', 'name="' . $xmlPackage . '/workflow/', (string) file_get_contents('psalm.xml'));
file_put_contents('psalm.xml', $psalm);

foreach (['phpstan.neon', 'psalm.xml', 'phpcs.xml.dist', 'rector.php', 'deptrac.yaml'] as $config) {
    $lines = explode("\n", (string) file_get_contents($config));
    $kept = array_filter($lines, static fn (string $line): bool => str_contains($line, $package) || str_contains($line, $xmlPackage) || preg_match($own, $line) !== 1);
    file_put_contents($config, implode("\n", $kept));
}

$profile = 'gates/src/Application/ProjectProfile.php';
$roots = (string) preg_replace("#, 'workflow/[a-z/.]+'#", '', (string) file_get_contents($profile));
file_put_contents($profile, (string) preg_replace("#^ *'workflow/[a-z/.]+',\n#m", '', $roots));

$suite = 'gates/src/Application/GateSuite.php';
file_put_contents($suite, (string) preg_replace('/^        (28|29) => \\[\\n.*?^        \\],\\n/ms', '', (string) file_get_contents($suite)));

$composer = str_replace('"url": "workflow"', '"url": "' . $package . '/workflow"', (string) file_get_contents('composer.json'));
$lines = array_filter(explode("\n", $composer), static fn (string $line): bool => !str_contains($line, '"gate:28"') && !str_contains($line, '"gate:29"'));
file_put_contents('composer.json', implode("\n", $lines));
