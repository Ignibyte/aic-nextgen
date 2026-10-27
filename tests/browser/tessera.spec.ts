// Tessera and AIC's server half, together in a browser (#173): what the
// protocol promises a person sees, on the compiled site in gate:23. Every test
// fails on a console error, so a script or style the page's
// Content-Security-Policy refuses fails it too; the browser's own line for a
// 4xx answer is not one, since the protocol answers 422 on purpose.

import { expect, test, type Page } from '@playwright/test';

type Marked = Window & { aicSameDocument?: boolean };

const errors: string[] = [];

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

test('a visit swaps regions, and back and forward restore them in the same document', async ({ page }) => {
    await page.goto('/');
    await mark(page);

    await page.locator('#demos a[href="/stock"]').click();
    await expect(page).toHaveURL(/\/stock$/);
    await expect(page.locator('[data-region="content"]').getByRole('heading', { name: 'Stock' })).toBeVisible();

    await page.goBack();
    await expect(page).toHaveURL(/\/$/);
    await expect(page.getByRole('heading', { name: 'Demos' })).toBeVisible();

    await page.goForward();
    await expect(page).toHaveURL(/\/stock$/);
    await expect(page.locator('[data-region="content"]').getByRole('heading', { name: 'Stock' })).toBeVisible();
    expect(await sameDocument(page)).toBe(true);
});

test('a refused greeting is answered in place, the URL kept', async ({ page }) => {
    await page.goto('/');
    await mark(page);

    const refused = page.waitForResponse((response) => response.url().endsWith('/hello') && response.request().method() === 'POST');
    await page.locator('#greeting-form').getByRole('button', { name: 'Greet' }).click();
    expect((await refused).status()).toBe(422);

    await expect(page).toHaveURL(/\/$/);
    await expect(page.locator('#greeting-form [data-error-summary]')).toHaveText('A name is required.');
    await expect(page.locator('#greeting-form [data-error-summary]')).toBeFocused();
    expect(await sameDocument(page)).toBe(true);
});

test('a greeting follows the redirect without a new document', async ({ page }) => {
    await page.goto('/');
    await mark(page);

    await page.locator('#greeting-form').getByLabel('Name').fill('Ada');
    await page.locator('#greeting-form').getByRole('button', { name: 'Greet' }).click();

    await expect(page).toHaveURL(/\/hello\/Ada$/);
    await expect(page.locator('#greetings')).toContainText('Hello, Ada');
    expect(await sameDocument(page)).toBe(true);
});

test('the ticks page counts the five streamed ticks', async ({ page }) => {
    await page.goto('/ticks');

    await expect(page.locator('#ticks-seen')).toHaveText('5', { timeout: 15000 });
    await expect(page.locator('ol[data-region="ticks"] li')).toHaveCount(5);
});

test.describe('with JavaScript off', () => {
    test.use({ javaScriptEnabled: false });

    test('the greeting form still submits as a plain form', async ({ page }) => {
        await page.goto('/');

        await page.locator('#greeting-form').getByLabel('Name').fill('Ada');
        await page.locator('#greeting-form').getByRole('button', { name: 'Greet' }).click();

        await expect(page).toHaveURL(/\/hello\/Ada$/);
        await expect(page.locator('#greetings')).toContainText('Hello, Ada');
    });
});
