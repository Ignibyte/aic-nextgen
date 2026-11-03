// A theme's page script (#239): themes/app/assets/theme.page.js loads on
// every page after Tessera, as a tracked, fingerprinted asset. A fragment
// answer whose copy of it differs, as after a deploy that changed the script,
// makes Tessera load the page whole (protocol section 9).

import { expect, test, type Page } from '@playwright/test';

type Marked = Window & { aicSameDocument?: boolean };

const SCRIPT = /(\/assets\/)[0-9a-f]{64}(\/themes\/app\/theme\.page\.js)/;

async function mark(page: Page): Promise<void> {
    await page.evaluate(() => {
        (window as Marked).aicSameDocument = true;
    });
}

async function sameDocument(page: Page): Promise<boolean | undefined> {
    return page.evaluate(() => (window as Marked).aicSameDocument);
}

test('the theme script runs on the page, tracked, after Tessera', async ({ page }) => {
    await page.goto('/');

    await expect(page.locator('html')).toHaveAttribute('data-theme-script', 'loaded');
    const sources = await page.locator('script[data-track]').evaluateAll((scripts) => scripts.map((script) => script.getAttribute('src') ?? ''));
    expect(sources).toHaveLength(2);
    expect(sources[0]).toMatch(/\/tessera-[0-9.]+\/tessera\.csp\.iife\.js$/);
    expect(sources[1]).toMatch(SCRIPT);
});

test('an unchanged theme script keeps an enhanced visit in the same document', async ({ page }) => {
    await page.goto('/');
    await mark(page);

    await page.locator('#demos a[href="/notes"]').click();
    await expect(page).toHaveURL(/\/notes$/);
    await expect(page.locator('[data-region="content"]').getByRole('heading', { name: 'Notes' })).toBeVisible();
    expect(await sameDocument(page)).toBe(true);
});

test('a changed theme script in a fragment answer loads the page whole', async ({ page }) => {
    await page.route('**/notes', async (route) => {
        const response = await route.fetch();
        const body = await response.text();
        const tessera = route.request().headers()['tessera-request'] !== undefined;

        await route.fulfill({ response, body: tessera ? body.replace(SCRIPT, `$1${'0'.repeat(64)}$2`) : body });
    });
    await page.goto('/');
    await mark(page);

    await page.locator('#demos a[href="/notes"]').click();
    await expect(page).toHaveURL(/\/notes$/);
    await expect(page.locator('[data-region="content"]').getByRole('heading', { name: 'Notes' })).toBeVisible();
    await expect(page.locator('html')).toHaveAttribute('data-theme-script', 'loaded');
    expect(await sameDocument(page)).toBeUndefined();
});
