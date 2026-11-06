<?php

declare(strict_types=1);

/*
 * Where mail goes out (#48), from the environment: AIC_MAIL_HOST, AIC_MAIL_PORT
 * and AIC_MAIL_FROM. The defaults reach DDEV's Mailpit inside the web
 * container.
 */

use AIC\Notification\Domain\MailConfig;

$setting = static function (string $name, string $default): string {
    $value = getenv($name);

    return is_string($value) && $value !== ''
        ? $value
        : $default;
};

return new MailConfig(
    host: $setting('AIC_MAIL_HOST', '127.0.0.1'),
    port: (int) $setting('AIC_MAIL_PORT', '1025'),
    from: $setting('AIC_MAIL_FROM', 'aic@app.test'),
);
