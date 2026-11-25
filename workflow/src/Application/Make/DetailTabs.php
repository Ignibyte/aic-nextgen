<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Workflow\Application\Console\ConsoleArguments;
use AIC\Workflow\Domain\Make\DetailPagePlan;
use AIC\Workflow\Domain\Make\EntityField;
use AIC\Workflow\Exception\WorkflowRefusal;

use function array_column;
use function array_key_exists;
use function count;
use function explode;
use function in_array;
use function preg_match;
use function sprintf;

/**
 * A detail page's tabs (#341), read from `--tabs=<tab>:<field>+<field>,…`:
 * each a lower-case name and the fields it shows, at most 8; and what the
 * make prints once the page is written.
 */
final readonly class DetailTabs
{
    /**
     * The most tabs a page has.
     */
    private const int MOST_TABS = 8;

    /**
     * @param string                      $tabs   What `--tabs` gave.
     * @param non-empty-list<EntityField> $fields The fields.
     *
     * @throws WorkflowRefusal When there are more than 8, a name is not lower case or repeats, or a tab names no field
     *                         or one that is not in `--fields`.
     *
     * @return non-empty-array<string, list<string>> Each tab's fields, by its name, in order; `details` with every
     *                                               field when none is given.
     */
    public function tabs(string $tabs, array $fields): array
    {
        $names = array_column($fields, 'name');

        if ($tabs === '') {
            return ['details' => $names];
        }

        $entries = explode(',', $tabs);

        if (count($entries) > self::MOST_TABS) {
            throw new WorkflowRefusal('--tabs names more than 8 tabs: a detail page has at most 8.', ConsoleArguments::USAGE);
        }

        $named = [];

        foreach ($entries as $entry) {
            [$tab, $shown] = explode(':', $entry, 2) + ['', ''];
            $named[self::name($tab, $named)] = self::shown($tab, $shown, $names);
        }

        return $named;
    }

    /**
     * @param DetailPagePlan $detailPagePlan The page.
     *
     * @return list<string> Who may see it, and the gate:23 steps to add.
     */
    public function lines(DetailPagePlan $detailPagePlan): array
    {
        return [
            sprintf(
                "The page is anyone's who opens %s: put it under a protected prefix and give its route needs: "
                . 'when not everyone may read %s.',
                $detailPagePlan->route(),
                $detailPagePlan->table,
            ),
            sprintf(
                'Add gate:23 steps to config/native-site.json: GET %s/<a key> (200) and once per tab with ?tab=<name> (200),'
                . ' with "Tessera-Targets: @%s" (200), and a key no row has (404).',
                $detailPagePlan->path,
                $detailPagePlan->anchor,
            ),
        ];
    }

    /**
     * @param string                      $tab   A tab's name.
     * @param array<string, list<string>> $named The tabs read so far.
     *
     * @throws WorkflowRefusal When it is not lower case, or repeats.
     *
     * @return string It.
     */
    private static function name(string $tab, array $named): string
    {
        if (preg_match('/^[a-z][a-z0-9_]{0,31}$/', $tab) !== 1) {
            throw new WorkflowRefusal(
                '--tabs names the tab "' . $tab . '": a tab is a lower-case word of at most 32 characters, then :<field>+<field>.',
                ConsoleArguments::USAGE,
            );
        }

        if (array_key_exists($tab, $named)) {
            throw new WorkflowRefusal('--tabs names the tab ' . $tab . ' twice.', ConsoleArguments::USAGE);
        }

        return $tab;
    }

    /**
     * @param string       $tab   The tab's name.
     * @param string       $shown What follows its `:`.
     * @param list<string> $names Every field's name.
     *
     * @throws WorkflowRefusal When it names no field, or one that is not in `--fields`.
     *
     * @return list<string> The fields it shows, in order.
     */
    private static function shown(string $tab, string $shown, array $names): array
    {
        if ($shown === '') {
            throw new WorkflowRefusal(
                '--tabs gives the tab ' . $tab . ' no field: write ' . $tab . ':<field>+<field>.',
                ConsoleArguments::USAGE,
            );
        }

        $fields = explode('+', $shown);

        foreach ($fields as $field) {
            if (!in_array($field, $names, true)) {
                throw new WorkflowRefusal(
                    '--tabs names ' . $field . ' in ' . $tab . ', which is not one of --fields.',
                    ConsoleArguments::USAGE,
                );
            }
        }

        return $fields;
    }
}
