<?php

declare(strict_types=1);

namespace App\Ledger\Form;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\Paragraph;
use AIC\Component\Domain\Table;
use AIC\Component\Domain\TableCell;
use AIC\Component\Domain\TableRow;
use AIC\Mcp\Domain\McpToolInfo;

/**
 * The tools the assistant may call (#332), as the page's table: each tool's
 * name, what it does, and whether it runs dry.
 */
final readonly class AssistantTools
{
    /**
     * @param list<McpToolInfo> $tools The tools the assistant may call.
     *
     * @return Component Them as a table, or a line saying there are none.
     */
    public function table(array $tools): Component
    {
        if ($tools === []) {
            return new Paragraph('Your assistant may call nothing: you hold none of the tools it may use.', 'assistant-tools');
        }

        $rows = [];

        foreach ($tools as $tool) {
            $dryRun = $tool->dryRun
                ? 'yes'
                : 'no';
            $rows[] = new TableRow([new TableCell($tool->name), new TableCell($tool->description), new TableCell($dryRun)]);
        }

        return new Table(['Tool', 'What it does', 'Dry run'], $rows, 'What your assistant may call');
    }
}
