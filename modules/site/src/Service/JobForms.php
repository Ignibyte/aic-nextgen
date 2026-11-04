<?php

declare(strict_types=1);

namespace App\Site\Service;

use AIC\Component\Domain\Button;
use AIC\Component\Domain\Form;
use AIC\Component\Domain\GuardFields;

/**
 * The jobs page's forms: a Discard per failed job (#262), then the buttons
 * that queue the demo job in each of its modes (#46).
 */
final readonly class JobForms
{
    /**
     * @param GuardFields $guardFields The visitor's CSRF fields.
     * @param list<int>   $failedIds   The failed jobs' ids, newest first.
     *
     * @return list<Form> The forms, in page order.
     */
    public function forms(GuardFields $guardFields, array $failedIds): array
    {
        $forms = [];

        foreach ($failedIds as $failedId) {
            $button = new Button('Discard #' . $failedId, 'submit', 'discard-' . $failedId);
            $forms[] = new Form('', '/jobs?mode=discard&id=' . $failedId, [$button], $guardFields);
        }

        $forms[] = self::queue($guardFields, 'succeed', 'Queue a job (retries once, then succeeds)');
        $forms[] = self::queue($guardFields, 'fail', 'Queue a failing job');
        $forms[] = self::queue($guardFields, 'broken', 'Queue a job that fails at once');

        return $forms;
    }

    /**
     * @param GuardFields $guardFields The visitor's CSRF fields.
     * @param string      $mode        The demo job's mode.
     * @param string      $label       The button's text.
     *
     * @return Form The form that queues it.
     */
    private static function queue(GuardFields $guardFields, string $mode, string $label): Form
    {
        return new Form('', '/jobs?mode=' . $mode, [new Button($label, 'submit', 'queue-' . $mode)], $guardFields);
    }
}
