<?php

declare(strict_types=1);

namespace AIC\Profiler\Application;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\Heading;
use AIC\Component\Domain\KeyedComponent;
use AIC\Component\Domain\Paragraph;
use AIC\Component\Domain\Stack;
use AIC\Component\Domain\Tab;
use AIC\Component\Domain\Tabs;
use AIC\Profiler\Domain\ProfileView;
use InvalidArgumentException;

use function sprintf;

/**
 * The profiler's detail page as components (#230): what the request was, a
 * way back and to the same browser's previous request, and its tabs, the tabs
 * and the open panel one Tessera target (`@profile-tab`) each tab answers in
 * place.
 */
final readonly class ProfilerDetailView
{
    /** The tabs in reading order: each its `?tab=` value and its label. */
    public const array TABS = [
        ['request', 'Request'],
        ['timeline', 'Timeline'],
        ['database', 'Database'],
        ['templates', 'Templates'],
        ['tessera', 'Tessera'],
    ];

    /** The anchor the tabs target. */
    private const string ANCHOR = 'profile-tab';

    /**
     * @param ProfilerPanels $profilerPanels Builds each tab's panel.
     */
    public function __construct(
        private ProfilerPanels $profilerPanels = new ProfilerPanels(),
    ) {
    }

    /**
     * @param ProfileView $profileView The profile.
     * @param string      $tab         The open tab, one of TABS' values.
     *
     * @throws InvalidArgumentException When a key or an anchor breaks the grammar.
     *
     * @return list<Component> The page's content.
     */
    public function content(ProfileView $profileView, string $tab): array
    {
        $profileRow = $profileView->profileRow;

        return [
            new Heading($profileRow->method . ' ' . $profileRow->path),
            new Paragraph($this->summary($profileView), ''),
            $this->way($profileView),
            new KeyedComponent(
                self::ANCHOR,
                new Stack([$this->tabs($profileRow->id, $tab), $this->profilerPanels->panel($profileView, $tab)]),
                '',
                self::ANCHOR,
            ),
        ];
    }

    /**
     * @param ProfileView $profileView The profile.
     *
     * @return string Its status, kind, handler, time, size, statements and user, in a line.
     */
    private function summary(ProfileView $profileView): string
    {
        $profileRow = $profileView->profileRow;

        return sprintf(
            '%d · %s · %s · %.1f ms · %d bytes · %d statement(s)%s',
            $profileRow->status,
            $profileRow->kind === '' ? 'no route' : $profileRow->kind,
            $profileView->handler === '' ? 'no handler' : $profileView->handler,
            $profileRow->microseconds / 1_000,
            $profileRow->bytes,
            $profileRow->statements,
            $profileView->user === '' ? '' : ' · user ' . $profileView->user,
        );
    }

    /**
     * @param ProfileView $profileView The profile.
     *
     * @return Tabs The way back to the list and, when there is one, to the same browser's previous request.
     */
    private function way(ProfileView $profileView): Tabs
    {
        $tabs = [new Tab('All requests', ProfilerDocuments::PATH)];

        if ($profileView->previous !== '') {
            $tabs[] = new Tab('Previous request', ProfilerDocuments::PATH . '/' . $profileView->previous);
        }

        return new Tabs($tabs, '', 'Requests');
    }

    /**
     * @param string $id  The profile's id.
     * @param string $tab The open tab.
     *
     * @return Tabs One tab per panel, the open one marked.
     */
    private function tabs(string $id, string $tab): Tabs
    {
        $tabs = [];

        foreach (self::TABS as [$key, $label]) {
            $tabs[] = new Tab($label, ProfilerDocuments::PATH . '/' . $id . '?tab=' . $key, $key === $tab);
        }

        return new Tabs($tabs, '@' . self::ANCHOR, 'Profile');
    }
}
