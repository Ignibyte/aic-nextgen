<?php

declare(strict_types=1);

use Gates\Application\ModuleBoundariesGate;
use Gates\Application\ModuleTreeReader;
use Gates\Application\TemplateBuild;
use Gates\Application\TemplateCompiler;
use Gates\Infrastructure\SystemProcessRunner;
use Gates\Templates\PlainTemplateReader;

require_once __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);
$templateBuild = new TemplateBuild(new PlainTemplateReader(), new TemplateCompiler(), $root);

exit((new ModuleBoundariesGate(new ModuleTreeReader(new SystemProcessRunner(), $templateBuild)))->run($root));
