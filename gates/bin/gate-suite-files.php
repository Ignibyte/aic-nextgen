<?php

declare(strict_types=1);

use Gates\Application\GateSuite;
use Gates\Application\SuiteComposer;
use Gates\Application\SuiteFilesGate;
use Gates\Application\SuiteStubs;
use Gates\Infrastructure\GatesPackage;
use Gates\Infrastructure\ProjectRoot;
use Gates\Phpmd\RuleRegistration;
use Gates\Sniffs\SniffRegistration;

require __DIR__ . '/project.php';

// gate:30 (#130): the tools' root configs are ignibyte/aic-gates' stubs, and composer.json carries the suite's lines.
$root = (new ProjectRoot())->path();
$package = (new GatesPackage())->path();

$registrations = [
    $package . '/config/phpcs-custom.xml' => (new SniffRegistration())->source(),
    $package . '/config/phpmd-custom.xml' => (new RuleRegistration())->source(),
];

exit((new SuiteFilesGate(new SuiteStubs($package), new SuiteComposer(new GateSuite($root, $package)), $registrations))->run($root));
