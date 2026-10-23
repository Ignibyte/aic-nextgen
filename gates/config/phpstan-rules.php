<?php

declare(strict_types=1);

use Gates\PhpStan\ControllerMarkupLiteralRule;
use Gates\PhpStan\ControllerReturnTypeRule;
use Gates\PhpStan\HtmlConstructionRule;
use Gates\PhpStan\HtmlMarkupCallableRule;
use Gates\PhpStan\HtmlMarkupReadRule;
use Gates\PhpStan\RenderTreeInputRule;
use Gates\PhpStan\RuleFactory;
use Gates\PhpStan\TaggedServiceTypeRule;

// Every factory below is consumed by PHPStan's real container, including its dependencies.
$services = ['aic.ruleFactory' => ['class' => RuleFactory::class]];

foreach ([HtmlMarkupCallableRule::class, HtmlMarkupReadRule::class, RenderTreeInputRule::class, TaggedServiceTypeRule::class] as $class) {
    $services[] = [
        'class' => $class,
        'factory' => ['@aic.ruleFactory', '__invoke'],
        'arguments' => [$class],
        'tags' => ['phpstan.rules.rule'],
    ];
}

return [
    'rules' => [
        HtmlConstructionRule::class,
        ControllerMarkupLiteralRule::class,
        ControllerReturnTypeRule::class,
    ],
    'services' => $services,
];
