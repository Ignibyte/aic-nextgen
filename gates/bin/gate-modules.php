<?php

declare(strict_types=1);

use Gates\Application\ModuleBoundariesGate;
use Gates\Application\ModuleTreeReader;
use Gates\Application\PresentationBuild;
use Gates\Application\TemplateBuild;
use Gates\Application\TemplateCompiler;
use Gates\Infrastructure\ConfiguredTheme;
use Gates\Infrastructure\ProjectRoot;
use Gates\Infrastructure\SystemProcessRunner;
use Gates\Templates\PlainTemplateReader;

require __DIR__ . '/project.php';

$root = (new ProjectRoot())->path();

$presentation = (new PresentationBuild($root))->plan((new ConfiguredTheme())->name($root));

$templateBuild = new TemplateBuild(new PlainTemplateReader(), new TemplateCompiler(), $root, $presentation);

exit((new ModuleBoundariesGate(new ModuleTreeReader(new SystemProcessRunner(), $templateBuild)))->run($root));
