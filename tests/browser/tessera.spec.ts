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

// The slice of markup that is a component's element, the one carrying its
// `data-t-c` (protocol 1, #189), nested same-named tags counted: what a
// fragment for that target must hold (#179).
function componentSlice(html: string, from: number, key: string): string {
    const mark = html.indexOf(` data-t-c="${key}"`, from);
    const start = html.lastIndexOf('<', mark);
    const name = /^<([A-Za-z][A-Za-z0-9-]*)/.exec(html.slice(start))?.[1] ?? '';
    const tag = new RegExp(`<(/?)${name}\\b[^>]*>`, 'g');
    let depth = 0;
    tag.lastIndex = start;

    for (let found = tag.exec(html); found !== null; found = tag.exec(html)) {
        depth += found[1] === '/' ? -1 : 1;

        if (depth === 0) {
            return html.slice(start, found.index + found[0].length);
        }
    }

    return '';
}

test('a link that targets an anchor swaps that component alone', async ({ page }) => {
    await page.goto('/stock');
    await page.evaluate(() => {
        (window as Window & { aicSameDocument?: boolean }).aicSameDocument = true;
    });
    const asked = page.waitForRequest((request) => request.headers()['tessera-targets'] !== undefined);

    await page.getByRole('link', { name: 'Show the last 3' }).click();

    expect((await asked).headers()['tessera-targets']).toBe('content/stock-list ' + (await page.locator('[data-t-anchor="stock-list"]').getAttribute('data-t-props')));
    await expect(page.locator('#stock-limit')).toHaveText('The last 3.');
    expect(await page.evaluate(() => (window as Window & { aicSameDocument?: boolean }).aicSameDocument)).toBe(true);
});

test('a fragment holds the same markup for its target as the whole document', async ({ page }) => {
    const whole = await (await page.request.get('/notes')).text();
    const fragment = await (await page.request.get('/notes', {
        headers: { 'Tessera-Request': 'visit', 'Tessera-Protocol': '1', 'Tessera-Layout': 'app', 'Tessera-Targets': 'content/c0' },
    })).text();
    const target = fragment.match(/<template data-target="content\/c0">([\s\S]*?)<\/template>/);

    // The component is its element (protocol 1); the newline after its template's root is outside it.
    expect(target?.[1].trimEnd()).toBe(componentSlice(whole, whole.indexOf('data-region="content"'), 'c0'));
    expect(target?.[1]).toContain('id="note-count"');
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
