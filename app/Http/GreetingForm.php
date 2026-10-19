<?php

declare(strict_types=1);

namespace App\Http;

use function htmlspecialchars;

/**
 * The form that posts a name to /hello, shown on the placeholder page and again,
 * with its error, when a post is refused.
 */
final readonly class GreetingForm
{
    /**
     * @param string $value The name to show in the field.
     * @param string $error The error to show; empty for none.
     *
     * @return string The form markup.
     */
    public function render(string $value, string $error): string
    {
        $message = $error === ''
            ? ''
            : '<p id="form-error">' . htmlspecialchars($error) . '</p>';

        return '<form id="greeting-form" method="post" action="/hello">' . $message
            . '<label for="name">Name</label> <input id="name" name="name" value="' . htmlspecialchars($value) . '">'
            . ' <button type="submit">Greet</button></form>';
    }
}
