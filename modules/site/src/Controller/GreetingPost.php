<?php

declare(strict_types=1);

namespace App\Site\Controller;

use AIC\Controller\Application\PageDocuments;
use AIC\Controller\Contracts\Page;
use AIC\Controller\Contracts\PageResponse;
use AIC\Controller\Contracts\SignalPatch;
use AIC\Controller\Domain\PageRedirect;
use App\Site\Form\GreetingForm;
use JsonException;
use LogicException;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function array_key_exists;
use function is_array;
use function is_string;
use function json_encode;
use function mb_strlen;
use function rawurlencode;
use function trim;

use const JSON_THROW_ON_ERROR;

/**
 * The greeting form's target, POST /hello: a name answers 303 to its greeting;
 * a blank one, or one over 40 characters, renders the form again in place,
 * with its error, as 422. Through Tessera that answer also patches the page's
 * signals (#253): `greetingProblem` to this refusal's reason, and
 * `greetingFirstProblem`, only if missing, to the first one's.
 */
final readonly class GreetingPost implements Page
{
    /**
     * @param PageDocuments $pageDocuments The document around the page.
     * @param GreetingForm  $greetingForm  The form, shown again on an error.
     */
    public function __construct(
        private PageDocuments $pageDocuments,
        private GreetingForm $greetingForm,
    ) {
    }

    /**
     * @param ServerRequestInterface $serverRequest The posted form.
     *
     * @throws LogicException When the required application state is unavailable.
     * @throws JsonException  When a refusal's reason cannot be written as JSON.
     *
     * @return PageResponse The redirect, or the form again as 422.
     */
    #[Override]
    public function page(ServerRequestInterface $serverRequest): PageResponse
    {
        $body = $serverRequest->getParsedBody();
        $name = is_array($body) && array_key_exists('name', $body) && is_string($body['name'])
            ? trim($body['name'])
            : '';

        $problem = match (true) {
            $name === '' => 'A name is required.',
            mb_strlen($name) > 40 => 'A name is at most 40 characters.',
            default => '',
        };

        if ($problem !== '') {
            $reason = json_encode($problem, JSON_THROW_ON_ERROR);

            return $this->pageDocuments->content([$this->greetingForm->form($serverRequest, $name, $problem)], 422, 'Greetings')
                ->withSignalPatch(new SignalPatch('page', '{"greetingProblem":' . $reason . '}'))
                ->withSignalPatch(new SignalPatch('page', '{"greetingFirstProblem":' . $reason . '}', true));
        }

        return new PageRedirect('/hello/' . rawurlencode($name));
    }
}
