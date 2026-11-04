<?php

declare(strict_types=1);

namespace App\Site\Job;

use AIC\Controller\Application\PageDocuments;
use AIC\Controller\Contracts\Job;
use AIC\Controller\Contracts\JobInput;
use AIC\Controller\Contracts\JobResponse;
use AIC\Controller\Domain\JobDone;
use AIC\Storage\Application\PageFiles;
use App\Theme\Components\LineList\LineList;
use App\Theme\Components\Panel\Panel;
use InvalidArgumentException;
use Override;
use RuntimeException;

/**
 * The kept page's job (#265): renders one page in the theme and keeps it as a private file, which
 * GET /kept-page serves. A real app keeps a page a visitor downloads later, such as a brochure.
 */
final readonly class KeptPageJob implements Job
{
    /**
     * Where the page is kept, in the private files.
     */
    public const string KEY = 'pages/kept.html';

    /**
     * @param PageDocuments $pageDocuments The theme's pages.
     * @param PageFiles     $pageFiles     Keeps them.
     */
    public function __construct(
        private PageDocuments $pageDocuments,
        private PageFiles $pageFiles,
    ) {
    }

    /**
     * @param JobInput $jobInput No payload.
     *
     * @throws InvalidArgumentException When the page or its key is invalid.
     * @throws RuntimeException         When a component has no template, or the file cannot be written.
     *
     * @return JobResponse Done, the page kept.
     */
    #[Override]
    public function run(JobInput $jobInput): JobResponse
    {
        $lineList = new LineList('kept', ['Rendered by a job, in the theme, and kept as a private file.'], 'None.');
        $this->pageFiles->put(self::KEY, $this->pageDocuments->content([new Panel('A kept page', $lineList)], 200, 'Kept page'));

        return new JobDone();
    }
}
