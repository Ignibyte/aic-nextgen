<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

use AIC\Component\Contracts\Component;

/**
 * A form around its fields and buttons.
 */
final readonly class Form implements Component
{
    /**
     * @param string          $id           The element id; empty for none.
     * @param string          $action       Where it posts.
     * @param list<Component> $fields       The hidden fields, alerts, fields and buttons, in order.
     * @param string          $formToken    The issued CSRF token value, prepared by the caller and rendered first with the honeypot (#42).
     * @param FormEncoding    $formEncoding How it posts; multipart for a form with a file (#44).
     */
    public function __construct(
        public string $id,
        public string $action,
        public array $fields,
        public string $formToken,
        public FormEncoding $formEncoding = FormEncoding::Urlencoded,
    ) {
    }
}
