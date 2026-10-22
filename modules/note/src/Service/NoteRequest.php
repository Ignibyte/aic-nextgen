<?php

declare(strict_types=1);

namespace App\Note\Service;

use App\Note\Form\PostedNote;
use Psr\Http\Message\ServerRequestInterface;

use function ctype_digit;
use function is_array;
use function is_string;
use function preg_match;
use function trim;

/**
 * What the note pages read from a request: the note's id from the route, and
 * the posted note, its title checked.
 */
final readonly class NoteRequest
{
    /**
     * @param ServerRequestInterface $serverRequest A request on `/notes/{id:int}`.
     *
     * @return int The note's id; 0, which no note has, when the attribute is missing.
     */
    public function noteId(ServerRequestInterface $serverRequest): int
    {
        $attribute = $serverRequest->getAttribute('id');

        return is_string($attribute) && ctype_digit($attribute)
            ? (int) $attribute
            : 0;
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted note form.
     *
     * @return PostedNote The title and the body, trimmed, and why the title is refused.
     */
    public function posted(ServerRequestInterface $serverRequest): PostedNote
    {
        $title = $this->field($serverRequest, 'title');

        return new PostedNote($title, $this->field($serverRequest, 'body'), $this->titleError($title));
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted form.
     * @param string                 $name          A field.
     *
     * @return string Its value, trimmed; empty when it is missing.
     */
    private function field(ServerRequestInterface $serverRequest, string $name): string
    {
        $body = $serverRequest->getParsedBody();
        $value = is_array($body)
            ? $body[$name] ?? ''
            : '';

        return is_string($value)
            ? trim($value)
            : '';
    }

    /**
     * @param string $title A posted title, trimmed.
     *
     * @return string Why it is refused; empty when it is fine.
     */
    private function titleError(string $title): string
    {
        if ($title === '') {
            return 'A title is required.';
        }

        return preg_match('/^.{1,200}$/u', $title) === 1
            ? ''
            : 'A title is at most 200 characters.';
    }
}
