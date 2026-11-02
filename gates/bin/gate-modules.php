<?php

declare(strict_types=1);

use Gates\Application\FolderCapRule;
use Gates\Application\KindFolderRule;
use Gates\Application\ModuleBoundariesGate;
use Gates\Application\ModuleReferenceRule;
use Gates\Application\ModuleShellRule;
use Gates\Application\ModuleTreeReader;
use Gates\Application\RequireGraphRule;
use Gates\Infrastructure\ProjectRoot;
use Gates\Infrastructure\SystemProcessRunner;
use Gates\Tables\MigrationOrderRule;
use Gates\Tables\TableOwnershipRule;

require __DIR__ . '/project.php';

$root = (new ProjectRoot())->path();

$rules = [
    new KindFolderRule(),
    new FolderCapRule(),
    new ModuleReferenceRule(),
    new RequireGraphRule(),
    new ModuleShellRule(),
    new TableOwnershipRule(),
    new MigrationOrderRule(),
];

exit((new ModuleBoundariesGate(new ModuleTreeReader(new SystemProcessRunner()), $rules))->run($root));
