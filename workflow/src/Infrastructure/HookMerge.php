<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Domain\RegexCaptures;
use stdClass;

use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_map;
use function array_values;
use function count;
use function get_object_vars;
use function in_array;
use function is_array;
use function is_string;
use function json_encode;
use function str_contains;

use const JSON_THROW_ON_ERROR;

/**
 * The guard's hooks merged into decoded settings (#105, Droost's merge): a
 * hook is the guard's when its command names `aic-guard.sh` and ends with
 * the guard's verb. Absent, its group is appended to the event's list; there
 * with another command or matcher, it is replaced in place; the same, it is
 * left. A guard hook whose event and verb the package no longer ships is
 * retired (#284), with a group or event it leaves empty. Nothing else in the
 * settings is touched, other hooks in a group included.
 */
final readonly class HookMerge
{
    use RegexCaptures;

    /**
     * @param stdClass $settings The project's settings, checked by ClaudeSettingsFile; changed in place.
     * @param stdClass $shipped  The package's `hooks.json`.
     *
     * @return array{added: int, upgraded: int, retired: int} How many of the guard's hooks were added, replaced and
     *                                                        removed.
     */
    public function merge(stdClass $settings, stdClass $shipped): array
    {
        $hooks = $this->object($settings, 'hooks');
        $shippedHooks = $this->object($shipped, 'hooks');
        $tally = ['added' => 0, 'upgraded' => 0, 'same' => 0];

        foreach ((new JsonReader($shippedHooks, 'shipped hooks'))->keys() as $key) {
            $tally = $this->event($hooks, $this->list($shippedHooks, $key), $key, $tally);
        }

        $retired = $this->retire($hooks, $shippedHooks);
        $settings->hooks = $retired['hooks'];

        return ['added' => $tally['added'], 'upgraded' => $tally['upgraded'], 'retired' => $retired['retired']];
    }

    /**
     * @param stdClass $object An object.
     * @param string   $key    One of its keys.
     *
     * @return stdClass The object under it; a new empty one when there is none.
     */
    public function object(stdClass $object, string $key): stdClass
    {
        $member = [get_object_vars($object)[$key] ?? null];

        return $member[0] instanceof stdClass
            ? $member[0]
            : new stdClass();
    }

    /**
     * @param stdClass $object An object.
     * @param string   $key    One of its keys, holding a list of objects.
     *
     * @return list<stdClass> The objects in it; empty when it is not a list.
     */
    public function list(stdClass $object, string $key): array
    {
        $member = [get_object_vars($object)[$key] ?? null];

        $objects = [];

        if (!is_array($member[0])) {
            return [];
        }

        foreach (array_keys($member[0]) as $index) {
            if (!($member[0][$index] instanceof stdClass)) {
                continue;
            }

            $objects[] = $member[0][$index];
        }

        return $objects;
    }

    /**
     * @param stdClass $hooks        The settings' hooks after the merge, each event a list of objects.
     * @param stdClass $shippedHooks The package's hooks.
     *
     * @return array{hooks: stdClass, retired: int} The hooks without the guard's whose event and verb are no longer
     *                                              shipped, an event left empty dropped; how many were removed.
     */
    private function retire(stdClass $hooks, stdClass $shippedHooks): array
    {
        $kept = new stdClass();
        $retired = 0;

        foreach ((new JsonReader($hooks, 'hooks'))->keys() as $event) {
            $left = $this->retireEvent($this->list($hooks, $event), $this->verbs($this->list($shippedHooks, $event)));
            $retired += $left['retired'];

            if ($left['retired'] > 0 && $left['groups'] === []) {
                continue;
            }

            $kept->{$event} = $left['groups'];
        }

        return ['hooks' => $kept, 'retired' => $retired];
    }

    /**
     * @param list<stdClass> $groups An event's groups in the settings.
     * @param list<string>   $verbs  The guard's verbs the package ships for the event.
     *
     * @return array{groups: list<stdClass>, retired: int} The groups left, one the removal emptied dropped; how many
     *                                                     of the guard's hooks were removed.
     */
    private function retireEvent(array $groups, array $verbs): array
    {
        $retired = 0;
        $left = [];

        foreach ($groups as $group) {
            $removed = $this->retireHooks($group, $verbs);
            $retired += $removed;

            if ($removed > 0 && $this->list($group, 'hooks') === []) {
                continue;
            }

            $left[] = $group;
        }

        return ['groups' => $left, 'retired' => $retired];
    }

    /**
     * @param stdClass     $group A group in the settings; its guard hooks for other verbs are removed in place.
     * @param list<string> $verbs The guard's verbs the package ships for the group's event.
     *
     * @return int How many hooks were removed.
     */
    private function retireHooks(stdClass $group, array $verbs): int
    {
        $hooks = $this->list($group, 'hooks');
        $left = array_values(array_filter($hooks, fn (stdClass $hook): bool => in_array($this->verb($hook), ['', ...$verbs], true)));

        if (count($left) === count($hooks)) {
            return 0;
        }

        $group->hooks = $left;

        return count($hooks) - count($left);
    }

    /**
     * @param list<stdClass> $groups The groups the package ships for an event.
     *
     * @return list<string> The guard's verb in each group's one hook.
     */
    private function verbs(array $groups): array
    {
        return array_map(fn (stdClass $group): string => $this->verb($this->list($group, 'hooks')[0] ?? new stdClass()), $groups);
    }

    /**
     * @param stdClass                                    $hooks  The settings' hooks; the event's list is set on it.
     * @param list<stdClass>                              $groups The groups the package ships for the event.
     * @param string                                      $event  The event.
     * @param array{added: int, upgraded: int, same: int} $tally  The count so far.
     *
     * @return array{added: int, upgraded: int, same: int} The count after the event.
     */
    private function event(stdClass $hooks, array $groups, string $event, array $tally): array
    {
        $list = $this->list($hooks, $event);

        foreach ($groups as $group) {
            $what = $this->place($list, $group);
            $list = $what === 'added'
                ? [...$list, $group]
                : $list;
            $tally[$what] += 1;
        }

        $hooks->{$event} = $list;

        return $tally;
    }

    /**
     * @param list<stdClass> $list  An event's groups in the settings; the guard's group in it is replaced in place.
     * @param stdClass       $group A group the package ships, its one hook the guard's.
     *
     * @return 'added'|'upgraded'|'same' `added` when the list does not hold the guard's hook for its verb (the caller appends it),
     *                                   `upgraded` when it was replaced, `same` when it was already the shipped one.
     */
    private function place(array $list, stdClass $group): string
    {
        $shippedHook = $this->list($group, 'hooks')[0] ?? new stdClass();
        $verb = $this->verb($shippedHook);

        foreach ($list as $existing) {
            $what = $verb === ''
                ? ''
                : $this->replace($existing, $group, $shippedHook, $verb);

            if ($what !== '') {
                return $what;
            }
        }

        return 'added';
    }

    /**
     * @param stdClass $existing    A group in the settings.
     * @param stdClass $group       The shipped group.
     * @param stdClass $shippedHook Its hook.
     * @param string   $verb        The guard's verb in it.
     *
     * @return ''|'upgraded'|'same' `upgraded` or `same` when the group held the guard's hook for the verb; empty otherwise.
     */
    private function replace(stdClass $existing, stdClass $group, stdClass $shippedHook, string $verb): string
    {
        $hooks = $this->list($existing, 'hooks');
        $index = $this->guardIndex($hooks, $verb);

        if ($index === null) {
            return '';
        }

        $before = json_encode($existing, JSON_THROW_ON_ERROR);
        $hooks[$index] = $shippedHook;
        $existing->hooks = $hooks;
        $this->matcher($existing, $group);

        return $before === json_encode($existing, JSON_THROW_ON_ERROR)
            ? 'same'
            : 'upgraded';
    }

    /**
     * @param list<stdClass> $hooks A group's hooks.
     * @param string         $verb  The guard's verb.
     *
     * @return int|null Where the guard's hook for the verb is among them; null when it is not.
     */
    private function guardIndex(array $hooks, string $verb): ?int
    {
        foreach ($hooks as $index => $hook) {
            if ($this->verb($hook) === $verb) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param stdClass $existing A group in the settings; it takes the shipped group's matcher, when that has one.
     * @param stdClass $group    The shipped group.
     */
    private function matcher(stdClass $existing, stdClass $group): void
    {
        $matcher = get_object_vars($group)['matcher'] ?? null;

        if (!is_string($matcher)) {
            return;
        }

        $existing->matcher = $matcher;
    }

    /**
     * @param stdClass $hook A hook.
     *
     * @return string The guard's verb when its command is the guard's; empty otherwise.
     */
    private function verb(stdClass $hook): string
    {
        $command = [get_object_vars($hook)['command'] ?? null];
        $guard = is_string($command[0]) && (str_contains($command[0], 'aic-guard.sh') || str_contains($command[0], 'aic-codex-guard.sh'));

        $match = $guard
            ? self::regexMatch('/\\s(edit|shell|record|stop|returned|pre|start|permission)\\s*$/', $command[0])
            : [];

        return array_key_exists(1, $match)
            ? $match[1]
            : '';
    }
}
