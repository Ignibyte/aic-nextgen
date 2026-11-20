<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Mcp\Attribute\PromptInfo;
use AIC\Mcp\Contracts\McpArgumentException;
use AIC\Mcp\Contracts\McpArguments;
use AIC\Mcp\Contracts\McpMessage;
use AIC\Mcp\Contracts\McpPrompt;
use AIC\Mcp\Domain\McpPromptArgument;
use AIC\Mcp\Domain\McpPromptGet;
use AIC\Mcp\Domain\McpPromptInfo;
use InvalidArgumentException;
use Override;

use function implode;
use function in_array;
use function strtr;

/**
 * Writes a prompt's adapter (#316), `<Prompt>Get`: built with the prompt by the
 * module's catalog, it gives the descriptor from `#[PromptInfo]` with its
 * arguments and passes the prompt's messages through.
 */
final readonly class McpPromptSource
{
    private const string GET = <<<'PHP'
        final readonly class {class} implements McpPromptGet
        {
            /**
             * @param McpPrompt $mcpPrompt The prompt.
             */
            public function __construct(
                private McpPrompt $mcpPrompt,
            ) {
            }

            /**
             * @return McpPromptInfo The prompt, from its #[PromptInfo].
             */
            #[Override]
            public function promptInfo(): McpPromptInfo
            {
                return new McpPromptInfo(
                    {name},
                    {description},
                    {needs},
                    [
        {arguments}
                    ],
                );
            }

            /**
             * @param McpArguments $mcpArguments The arguments, checked against the info's.
             *
             * @throws McpArgumentException When the prompt cannot read one it needs.
             *
             * @return list<McpMessage> Its messages.
             */
            #[Override]
            public function promptMessages(McpArguments $mcpArguments): array
            {
                return $this->mcpPrompt->messages($mcpArguments);
            }
        }
        PHP;

    /**
     * @param PhpClassFile $phpClassFile Emits checked generated source.
     * @param McpCode      $mcpCode      The code pieces.
     */
    public function __construct(
        private PhpClassFile $phpClassFile = new PhpClassFile(),
        private McpCode $mcpCode = new McpCode(),
    ) {
    }

    /**
     * @param class-string $class      A prompt.
     * @param PromptInfo   $promptInfo Its attribute.
     * @param string       $from       Its module's `src/Mcp/`.
     *
     * @throws InvalidArgumentException When imports conflict.
     *
     * @return string Its adapter's file.
     */
    public function get(string $class, PromptInfo $promptInfo, string $from): string
    {
        $source = strtr(self::GET, [
            '{arguments}' => implode("\n", $this->arguments($promptInfo)),
            '{class}' => $this->mcpCode->short($class) . 'Get',
            '{description}' => $this->mcpCode->value($promptInfo->description),
            '{name}' => $this->mcpCode->value($promptInfo->name),
            '{needs}' => $this->mcpCode->value($promptInfo->needs),
        ]);
        $classes = [McpArgumentException::class, McpArguments::class, McpMessage::class, McpPromptArgument::class];
        $classes = [...$classes, McpPromptGet::class, McpPromptInfo::class];
        $classes = [...$classes, McpPrompt::class, Override::class];

        $docblock = $this->phpClassFile->generated($from, 'the ' . $promptInfo->name . " prompt's adapter (#316).");

        return $this->phpClassFile->file('App\\Generated', $classes, [], $docblock, $source);
    }

    /**
     * @param PromptInfo $promptInfo A prompt's attribute.
     *
     * @return list<string> Each argument's line in the descriptor.
     */
    private function arguments(PromptInfo $promptInfo): array
    {
        $arguments = [];

        foreach ($promptInfo->arguments as $name => $description) {
            $args = [$this->mcpCode->value($name), $this->mcpCode->value($description)];
            $args = in_array($name, $promptInfo->optional, true)
                ? [...$args, 'false']
                : $args;
            $arguments[] = $this->mcpCode->call('new McpPromptArgument', $args, 16, '', ',');
        }

        return $arguments;
    }
}
