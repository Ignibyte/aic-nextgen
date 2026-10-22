<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\HouseRule;
use AIC\Workflow\Domain\RuleChange;

use function array_map;
use function json_encode;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * A house rule's input (#104), `aic.house-rule/1`: the rule, the phase, the
 * project's root and autoloader, and each changed path with its base and
 * head as files.
 */
final readonly class RuleInput
{
    /**
     * @param string $root     The project's root.
     * @param string $autoload The project's autoloader.
     */
    public function __construct(
        private string $root,
        private string $autoload,
    ) {
    }

    /**
     * @param HouseRule        $houseRule The rule.
     * @param string           $phase     The phase it runs at, or `probe`.
     * @param list<RuleChange> $changes   What it judges.
     *
     * @return string The input, as JSON.
     */
    public function json(HouseRule $houseRule, string $phase, array $changes): string
    {
        return json_encode([
            'autoload' => $this->autoload,
            'changed' => array_map(static fn (RuleChange $ruleChange): array => $ruleChange->toArray(), $changes),
            'phase' => $phase,
            'project_root' => $this->root,
            'rule' => $houseRule->id,
            'schema' => 'aic.house-rule/1',
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
