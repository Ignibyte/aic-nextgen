<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Workflow\Application\Console\ConsoleArguments;
use AIC\Workflow\Application\Make\Source\WebhookSource;
use AIC\Workflow\Domain\Console\CommandUsage;
use AIC\Workflow\Domain\Console\ParsedArguments;
use AIC\Workflow\Domain\Make\MakeRecipe;
use AIC\Workflow\Exception\WorkflowRefusal;
use InvalidArgumentException;
use Override;

use function sprintf;
use function strtoupper;

/**
 * `bin/aic make:webhook <module> <Name>Webhook <path> [--scheme=hmac-sha256]
 * [--dry-run] [--json]` (#321): a POST Api that queues a job with a signed
 * delivery's body and answers 202, the delivery's value and the job. The
 * manifest routes the Api and names the job; the webhook's signature header
 * and secret go in config/app.php, which the make prints and leaves to the
 * agent: until then the framework does not take a delivery there.
 */
final readonly class MakeWebhookCommand implements Command
{
    /**
     * The usage.
     */
    private const string USAGE = 'make:webhook <module> <Name>Webhook <path> [--scheme=hmac-sha256] [--dry-run] [--json]';

    /**
     * The one scheme the framework checks (its `WebhookScheme::HmacSha256`).
     */
    private const string SCHEME = 'hmac-sha256';

    /**
     * @param MakeRun       $makeRun       The run.
     * @param MakeNames     $makeNames     The rules.
     * @param MakeCalls     $makeCalls     The route and job calls.
     * @param WebhookSource $webhookSource Writes the files.
     */
    public function __construct(
        private MakeRun $makeRun,
        private MakeNames $makeNames = new MakeNames(),
        private MakeCalls $makeCalls = new MakeCalls(),
        private WebhookSource $webhookSource = new WebhookSource(),
    ) {
    }

    /**
     * @param CommandInput $commandInput The line.
     *
     * @throws InvalidArgumentException When the response exit code is outside 0 to 255.
     *
     * @return CommandResponse What it wrote.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        return $this->makeRun->run(
            $commandInput,
            new CommandUsage('make:webhook', self::USAGE, ['dry-run'], ['scheme'], 3),
            $this->recipe(...),
        );
    }

    /**
     * @param ParsedArguments $parsedArguments The line.
     *
     * @throws WorkflowRefusal When a name, the path or the scheme breaks a rule.
     *
     * @return MakeRecipe The webhook, its payload and its job, and the config line to add.
     */
    private function recipe(ParsedArguments $parsedArguments): MakeRecipe
    {
        $this->scheme($parsedArguments->flag('scheme'));
        [$module, $namespace, $class] = $this->makeNames->target('Webhook', $parsedArguments);
        $stem = $this->stem($class);
        $path = $this->makeNames->path($parsedArguments->positional(2));
        $job = $this->makeNames->name($this->makeNames->kebab($stem) . '-webhook');
        $files = $this->webhookSource->files('modules/' . $module . '/src/', $namespace, $stem, $path, $job);
        $manifestCall = $this->makeCalls->both(
            $this->makeCalls->route('Post', $path, $class, $namespace),
            $this->makeCalls->named('job', $job, $stem . 'WebhookJob', $namespace),
        );
        $line = sprintf(
            "Add the webhook to config/app.php's webhooks: '%s' => new Webhook('X-Signature', \$setting('%s', '')).",
            $path,
            'AIC_WEBHOOK_' . strtoupper($this->makeNames->snake($stem)) . '_SECRET',
        );

        return new MakeRecipe($module, $class, $files, $manifestCall, ['route POST ' . $path, 'job ' . $job], lines: [$line]);
    }

    /**
     * @param string $scheme What `--scheme` gave.
     *
     * @throws WorkflowRefusal When it is neither empty nor `hmac-sha256`.
     */
    private function scheme(string $scheme): void
    {
        if ($scheme !== '' && $scheme !== self::SCHEME) {
            throw new WorkflowRefusal(
                '--scheme takes hmac-sha256, the hex HMAC-SHA256 of the body under the secret.',
                ConsoleArguments::USAGE,
            );
        }
    }

    /**
     * @param string $class The webhook's class, ending in `Webhook`.
     *
     * @throws WorkflowRefusal When it is `Webhook` alone.
     *
     * @return string Its name without `Webhook`, which names the payload and the job.
     */
    private function stem(string $class): string
    {
        if ($class === 'Webhook') {
            throw new WorkflowRefusal('the webhook "Webhook" needs a name before Webhook: name who sends it.', ConsoleArguments::USAGE);
        }

        return $this->makeNames->stem($class, 'Webhook');
    }
}
