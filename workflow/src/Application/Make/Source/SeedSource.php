<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make\Source;

use AIC\Workflow\Application\Make\MakeTemplates;
use AIC\Workflow\Domain\Make\SeedPlan;
use AIC\Workflow\Exception\WorkflowRefusal;

use function implode;
use function ksort;
use function lcfirst;

/**
 * The two files `make:seed` writes (#320): the seed command, its first row a
 * literal of each field's type by column name (PHPCS sorts keyed arrays), and
 * the repository it saves through, its
 * entity type method named for the seed so two made repositories do not
 * read as clones.
 */
final readonly class SeedSource
{
    /**
     * @param MakeTemplates $makeTemplates The built-in templates (#301).
     */
    public function __construct(
        private MakeTemplates $makeTemplates = new MakeTemplates(),
    ) {
    }

    /**
     * @param SeedPlan $seedPlan The seed.
     *
     * @throws WorkflowRefusal When its template is missing.
     *
     * @return string The seed command's bytes.
     */
    public function seed(SeedPlan $seedPlan): string
    {
        $examples = [];

        foreach ($seedPlan->fields as $entityField) {
            $examples[$entityField->name] = "            '" . $entityField->name . "' => " . $entityField->example() . ",\n";
        }

        ksort($examples);
        $row = implode('', $examples);

        return $this->makeTemplates->render('seed', 'seed.php.tpl', [
            'class' => $seedPlan->seed(),
            'name' => $seedPlan->name,
            'namespace' => $seedPlan->namespace,
            'repository' => $seedPlan->repository(),
            'rows' => "        [\n" . $row . '        ],',
            'table' => $seedPlan->table,
            'unique' => $seedPlan->unique,
            'variable' => lcfirst($seedPlan->repository()),
        ]);
    }

    /**
     * @param SeedPlan $seedPlan The seed.
     *
     * @throws WorkflowRefusal When its template is missing.
     *
     * @return string Its repository's bytes.
     */
    public function repository(SeedPlan $seedPlan): string
    {
        $fields = '';

        foreach ($seedPlan->fields as $entityField) {
            $fields .= '            ' . $entityField->field() . ",\n";
        }

        return $this->makeTemplates->render('seed', 'repository.php.tpl', [
            'class' => $seedPlan->repository(),
            'fields' => $fields,
            'namespace' => $seedPlan->namespace,
            'seed' => $seedPlan->seed(),
            'table' => $seedPlan->table,
            'type' => lcfirst($seedPlan->stem) . 'Type',
            'unique' => $seedPlan->unique,
        ]);
    }
}
