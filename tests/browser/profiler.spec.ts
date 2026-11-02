// The profiler as a person meets it (#230, #231): its pages and the toolbar
// every page carries, on the compiled site in gate:23's profiled pass (#234).
// It runs only with AIC_PROFILER=1; the plain pass never selects it. Every
// test fails on a console error, as tessera.spec.ts's do.

import { expect, test, type Page } from '@playwright/test';

type Marked = Window & { aicSameDocument?: boolean };

const errors: string[] = [];

test.skip(process.env.AIC_PROFILER !== '1', 'the profiler is off');

test.beforeEach(({ page }) => {
    errors.length = 0;
    page.on('console', (message) => {
        if (message.type() === 'error' && !message.text().startsWith('Failed to load resource: the server responded with a status of 4')) {
            errors.push(message.text());
        }
    });
    page.on('pageerror', (error) => errors.push(error.message));
});

test.afterEach(() => {
    expect(errors).toEqual([]);
});

async function mark(page: Page): Promise<void> {
    await page.evaluate(() => {
        (window as Marked).aicSameDocument = true;
    });
}

async function sameDocument(page: Page): Promise<boolean | undefined> {
    return page.evaluate(() => (window as Marked).aicSameDocument);
}

test('the list filters by kind in place', async ({ page }) => {
    await page.goto('/');
    await page.goto('/_profiler');
    await expect(page.getByRole('heading', { name: 'Requests' })).toBeVisible();
    await expect(page.locator('table.aic-table tbody tr').first()).toBeVisible();
    await mark(page);

    await page.getByRole('navigation', { name: 'Kind' }).getByRole('link', { name: 'Page' }).click();

    await expect(page).toHaveURL(/\/_profiler\?kind=Page$/);
    await expect(page.getByRole('navigation', { name: 'Kind' }).getByRole('link', { name: 'Page' })).toHaveAttribute('aria-current', 'page');
    expect(await sameDocument(page)).toBe(true);
});

test('a Tessera visit reads back in its profile', async ({ page }) => {
    await page.goto('/');
    await page.getByRole('link', { name: 'Notes', exact: true }).first().click();
    await expect(page).toHaveURL(/\/notes$/);

    await page.goto('/_profiler?path=/notes');
    const visit = page.locator('table.aic-table tbody tr', { hasText: 'visit' }).first();
    await visit.getByRole('link').click();
    await page.getByRole('navigation', { name: 'Profile' }).getByRole('link', { name: 'Tessera' }).click();

    await expect(page.getByRole('table', { name: 'Asked' })).toContainText('visit');
    await expect(page.locator('pre.aic-code')).toContainText('tessera-csrf-token" content="redacted"');

    await page.getByRole('navigation', { name: 'Profile' }).getByRole('link', { name: 'Timeline' }).click();
    await expect(page.locator('figure.aic-timeline svg rect')).toHaveCount(4);
});

test('the toolbar lists a visit live and outlines what it patched', async ({ page }) => {
    await page.goto('/notes');
    const toolbar = page.locator('aside.aic-toolbar');
    await expect(toolbar.locator('summary')).toContainText('GET /notes');

    await toolbar.locator('summary').click();
    const patched = page.waitForSelector('[data-region="content"][data-tessera-patched]', { state: 'attached' });
    await page.locator('a[href^="/notes/"]').first().click();
    await patched;

    await expect(toolbar.locator('.aic-toolbar-exchanges a').last()).toContainText('visit GET /notes/');
    await expect(page.locator('#aic-highlight')).toBeChecked();
});
