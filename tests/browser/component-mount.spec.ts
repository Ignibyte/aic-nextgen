// Component scripts (#315): the ledger Meter names its module script in
// Tessera's `data-mount`. The script is fetched only on a page that holds the
// component; it draws one bar per level when Tessera mounts it; a visit that
// keeps the level keeps what it drew without mounting again; a visit that
// changes the level mounts it again on the same element; and a visit that
// drops the meter runs its cleanup and unmount. meter.js logs each call in
// `globalThis.meterLog`. It fails on a console error, as tessera.spec.ts's
// tests do.

import { expect, test, type Page } from '@playwright/test';

const errors: string[] = [];

test.beforeEach(({ page }) => {
    errors.length = 0;
    page.on('console', (message) => {
        if (message.type() === 'error') {
            errors.push(message.text());
        }
    });
    page.on('pageerror', (error) => errors.push(error.message));
});

test.afterEach(() => {
    expect(errors).toEqual([]);
});

async function log(page: Page): Promise<string[]> {
    return page.evaluate(() => (globalThis as { meterLog?: string[] }).meterLog ?? []);
}

test('a component script mounts, mounts again after a patch, and unmounts when the component goes', async ({ page }) => {
    const fetched: string[] = [];
    page.on('request', (request) => {
        if (request.url().includes('/components/meter.js')) {
            fetched.push(request.url());
        }
    });

    await page.goto('/');
    await expect(page.locator('#demos')).toBeVisible();
    expect(fetched).toEqual([]);

    await page.goto('/ledger/meter?level=3');
    await expect(page.locator('#meter .meter-bar')).toHaveCount(3);
    expect(await log(page)).toEqual(['mount 3']);
    const meter = await page.locator('#meter').elementHandle();

    await page.locator('#meter-same').click();
    await expect(page).toHaveURL(/level=3$/);
    await expect(page.locator('#meter .meter-bar')).toHaveCount(3);
    expect(await log(page)).toEqual(['mount 3']);

    await page.locator('#meter-more').click();
    await expect(page).toHaveURL(/level=4$/);
    await expect(page.locator('#meter .meter-bar')).toHaveCount(4);
    expect(await log(page)).toEqual(['mount 3', 'cleanup', 'unmount', 'mount 4']);
    expect(await meter?.evaluate((element) => element.isConnected)).toBe(true);

    await page.locator('#meter-hide').click();
    await expect(page).toHaveURL(/level=0$/);
    await expect(page.locator('#meter')).toHaveCount(0);
    expect(await log(page)).toEqual(['mount 3', 'cleanup', 'unmount', 'mount 4', 'cleanup', 'unmount']);
    expect(fetched).toHaveLength(1);
});
