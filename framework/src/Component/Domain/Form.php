<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

use AIC\Component\Contracts\Component;

/**
 * A form around its fields and buttons, its guard fields first (#246).
 */
final readonly class Form implements Component
{
    /**
     * @param string          $id           The element id; empty for none.
     * @param string          $action       Where it posts.
     * @param list<Component> $fields       The hidden fields, alerts, fields and buttons, in order.
     * @param GuardFields     $guardFields  The CSRF token and the honeypot, rendered before the fields (#42, #246).
     * @param FormEncoding    $formEncoding How it posts; multipart for a form with a file (#44).
     */
    public function __construct(
        public string $id,
        public string $action,
        public array $fields,
        public GuardFields $guardFields,
        public FormEncoding $formEncoding = FormEncoding::Urlencoded,
    ) {
    }
}
