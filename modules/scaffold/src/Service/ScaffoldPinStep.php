<?php

declare(strict_types=1);

namespace App\Scaffold\Service;

use AIC\Form\Application\PostedFields;
use Psr\Http\Message\ServerRequestInterface;

use function trim;

/**
 * What POST /scaffold/scaffold-pin does, made by bin/aic make:page-action (#322): one call,
 * which answers why it refused or nothing. The made one refuses a blank
 * `value`; put the action's own call in its place.
 */
final readonly class ScaffoldPinStep
{
    /**
     * @param PostedFields $postedFields Reads the posted fields.
     */
    public function __construct(
        private PostedFields $postedFields = new PostedFields(),
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted form.
     *
     * @return string Why it was refused, a sentence; empty when it was done.
     */
    public function run(ServerRequestInterface $serverRequest): string
    {
        return trim($this->postedFields->value($serverRequest, 'value')) === ''
            ? 'A value is required.'
            : '';
    }
}
