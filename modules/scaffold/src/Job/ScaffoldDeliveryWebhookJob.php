<?php

declare(strict_types=1);

namespace App\Scaffold\Job;

use AIC\Controller\Contracts\Job;
use AIC\Controller\Contracts\JobInput;
use AIC\Controller\Contracts\JobResponse;
use AIC\Controller\Domain\JobDone;
use AIC\Controller\Domain\JobFailed;
use App\Scaffold\Service\ScaffoldDeliveryPayload;
use Override;

/**
 * The job `scaffold-delivery-webhook`, made by bin/aic make:webhook (#321): one
 * delivery to /scaffold/delivery, queued by ScaffoldDeliveryWebhook. An empty body
 * fails; the work on the delivery goes here.
 */
final readonly class ScaffoldDeliveryWebhookJob implements Job
{
    /**
     * @param JobInput $jobInput The payload: the delivery's body.
     *
     * @return JobResponse Done, or failed on an empty body.
     */
    #[Override]
    public function run(JobInput $jobInput): JobResponse
    {
        $scaffoldDeliveryPayload = new ScaffoldDeliveryPayload($jobInput->value('body', ''));

        return $scaffoldDeliveryPayload->body === ''
            ? new JobFailed('The delivery had no body.')
            : new JobDone();
    }
}
