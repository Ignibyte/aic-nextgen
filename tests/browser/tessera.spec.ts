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

test('a hover prefetch of a prefetchable page is the click\'s answer: one request', async ({ page }) => {
    await page.goto('/');
    await mark(page);
    const requests: string[] = [];
    page.on('request', (request) => {
        if (new URL(request.url()).pathname === '/stock') {
            requests.push(request.headers()['cache-control'] ?? '');
        }
    });

    // The stock page answers private, no-cache (#254), so Tessera keeps the hover's answer for the click.
    const prefetched = page.waitForResponse((response) => new URL(response.url()).pathname === '/stock');
    await page.locator('#demos a[href="/stock"]').hover();
    expect((await prefetched).headers()['cache-control']).toBe('private, no-cache');
    await page.locator('#demos a[href="/stock"]').click();

    await expect(page).toHaveURL(/\/stock$/);
    await expect(page.locator('[data-region="content"]').getByRole('heading', { name: 'Stock' })).toBeVisible();
    expect(requests).toHaveLength(1);
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

test('a refused greeting patches the page signals, the first reason only if missing', async ({ page }) => {
    await page.goto('/');
    await mark(page);
    const form = page.locator('#greeting-form');

    const blank = page.waitForResponse((response) => response.url().endsWith('/hello') && response.request().method() === 'POST');
    await form.getByRole('button', { name: 'Greet' }).click();
    expect((await blank).status()).toBe(422);
    await expect(page.locator('#greeting-last')).toHaveText('A name is required.');
    await expect(page.locator('#greeting-first')).toHaveText('A name is required.');

    // A second refusal, for another reason: the plain patch replaces the latest, and the
    // only-if-missing patch leaves the first as it was (#253).
    const long = page.waitForResponse((response) => response.url().endsWith('/hello') && response.request().method() === 'POST');
    await page.locator('#greeting-form').getByLabel('Name').fill('A'.repeat(41));
    await page.locator('#greeting-form').getByRole('button', { name: 'Greet' }).click();
    expect((await long).status()).toBe(422);
    await expect(page.locator('#greeting-last')).toHaveText('A name is at most 40 characters.');
    await expect(page.locator('#greeting-first')).toHaveText('A name is required.');
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

// The ticks stream pauses while the page is hidden (#259): the page goes hidden the moment the
// first tick lands, the stream closes, and visible again it resumes after the last tick seen.
test('the ticks stream closes while the page is hidden and resumes after the last tick seen', async ({ page }) => {
    await page.addInitScript(() => {
        const state = { hidden: false };
        Object.defineProperty(document, 'visibilityState', { configurable: true, get: () => (state.hidden ? 'hidden' : 'visible') });
        Object.defineProperty(document, 'hidden', { configurable: true, get: () => state.hidden });
        Object.assign(window, {
            aicShow: () => {
                state.hidden = false;
                document.dispatchEvent(new Event('visibilitychange'));
            },
        });
        document.addEventListener('DOMContentLoaded', () => {
            const list = document.querySelector('ol[data-region="ticks"]');
            const observer = new MutationObserver(() => {
                if (list?.querySelector('li') === null) {
                    return;
                }

                observer.disconnect();
                state.hidden = true;
                document.dispatchEvent(new Event('visibilitychange'));
            });

            if (list !== null) {
                observer.observe(list, { childList: true });
            }
        });
    });
    const streams: { lastEventId: string; pause: Promise<string> }[] = [];
    page.on('request', (request) => {
        if (new URL(request.url()).pathname === '/events') {
            streams.push({
                lastEventId: request.headers()['last-event-id'] ?? '',
                pause: request.response().then((response) => response?.headers()['tessera-stream-pause'] ?? ''),
            });
        }
    });

    await page.goto('/ticks');
    await expect.poll(() => streams.length).toBe(1);
    expect(await streams[0].pause).toBe('hidden');
    await expect(page.locator('ol[data-region="ticks"] li').first()).toBeVisible();

    // Hidden: the connection is closed, and no tick arrives for a while.
    const seen = await page.locator('ol[data-region="ticks"] li').count();
    expect(seen).toBeLessThan(5);
    await page.waitForTimeout(1500);
    await expect(page.locator('ol[data-region="ticks"] li')).toHaveCount(seen);
    expect(streams).toHaveLength(1);

    // Visible again: one reconnect, after the last tick the page saw, and the rest arrive.
    await page.evaluate(() => {
        (window as Window & { aicShow?: () => void }).aicShow?.();
    });
    await expect.poll(() => streams.length).toBe(2);
    expect(streams[1].lastEventId).toBe(String(seen));
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

    const props = await page.locator('[data-t-anchor="stock-list"]').getAttribute('data-t-props');
    // Protocol 2 (#194): the list's path inside its panel, its props, then its anchor.
    expect((await asked).headers()['tessera-targets']).toMatch(new RegExp(`^content/\\S+ ${props} @stock-list$`));
    await expect(page.locator('#stock-limit')).toHaveText('The last 3.');
    expect(await page.evaluate(() => (window as Window & { aicSameDocument?: boolean }).aicSameDocument)).toBe(true);
});

// A patch the page scopes by the list's anchor lands in the list's own scope (#260): the answer names the
// path Tessera sent with the anchor, never `@stock-list`, and nothing logs an error.
test('a signal patch scoped by the list anchor lands in the list after its refresh', async ({ page }) => {
    const errors: string[] = [];
    page.on('console', (message) => {
        if (message.type() === 'error') {
            errors.push(message.text());
        }
    });
    await page.goto('/stock');
    await expect(page.locator('#stock-refreshed')).toHaveText('');
    const answered = page.waitForResponse((response) => (response.request().headers()['tessera-targets'] ?? '').includes('@stock-list'));

    await page.getByRole('link', { name: 'Show the last 3' }).click();

    const body = await (await answered).text();
    // The list's path is the one its template answers: positional, so read, never written here.
    const path = /<template data-target="([^"]+)">/.exec(body)?.[1] ?? '';
    expect(path).not.toBe('');
    expect(body).toContain(`<script type="application/json" data-scope="${path}">{"stockRefreshed":true}</script>`);
    expect(body).not.toContain('data-scope="@');
    await expect(page.locator('#stock-limit')).toHaveText('The last 3.');
    await expect(page.locator('#stock-refreshed')).toHaveText('Refreshed in place.');
    expect(errors).toEqual([]);
});

test('a list built from its source carries its latest line, and its refresh leaves the totals alone', async ({ page }) => {
    await page.goto('/stock?q=a');
    await mark(page);
    const totals = await page.locator('#stock-totals').textContent();

    await page.getByRole('link', { name: 'Show the last 3' }).click();

    await expect(page.locator('[data-t-anchor="stock-list"] #stock-limit')).toHaveText('The last 3.');
    await expect(page.locator('[data-t-anchor="stock-list"] #stock-latest')).toHaveText(/^(Latest: .+\.|Nothing in stock yet\.)$/);
    await expect(page.locator('#stock-totals')).toHaveText(totals ?? '');
    expect(await sameDocument(page)).toBe(true);
});

test('a poll of the list answers 204 while nothing changed, and the list stays', async ({ page }) => {
    await page.goto('/stock?limit=3');
    await mark(page);
    const list = await page.locator('[data-t-anchor="stock-list"]').innerHTML();

    const polled = await page.waitForResponse((response) => response.request().headers()['tessera-request'] === 'refresh');

    const props = await page.locator('[data-t-anchor="stock-list"]').getAttribute('data-t-props');
    expect(polled.request().headers()['tessera-targets']).toMatch(new RegExp(`^content/\\S+ ${props} @stock-list$`));
    expect(polled.status()).toBe(204);
    expect(await page.locator('[data-t-anchor="stock-list"]').innerHTML()).toBe(list);
    expect(await sameDocument(page)).toBe(true);
});

test('a fragment holds the same markup for its target as the whole document', async ({ page }) => {
    const whole = await (await page.request.get('/notes')).text();
    // The content region's first child, by the key the page printed: no test spells a positional path.
    const region = whole.indexOf('data-region="content"');
    const key = whole.slice(region).match(/data-t-c="([^"]+)"/)?.[1] ?? '';
    const path = ['content', key].join('/');
    const fragment = await (await page.request.get('/notes', {
        headers: { 'Tessera-Request': 'visit', 'Tessera-Protocol': '2', 'Tessera-Layout': 'app', 'Tessera-Targets': path },
    })).text();
    const start = fragment.indexOf(`<template data-target="${path}">`);
    const target = start < 0 ? undefined : fragment.slice(start).match(/^<template[^>]*>([\s\S]*?)<\/template>/);

    // The component is its element (protocol 1 and 2); the newline after its template's root is outside it.
    expect(key).not.toBe('');
    expect(target?.[1].trimEnd()).toBe(componentSlice(whole, region, key));
    expect(target?.[1]).toContain('id="note-count"');
});

test('one answer removes two gone components and still patches the list after them', async ({ page }) => {
    await page.goto('/stock');
    await mark(page);

    // Two components the stock page never renders, as if deleted since it loaded, and an action naming
    // them and the list. The server answers each gone one removed (#247), then the list; Tessera 0.4.1
    // and older dropped every fragment after the first removal (Tessera #41, fixed in 0.4.2).
    await page.evaluate(() => {
        const content = document.querySelector('[data-region="content"]');
        content?.insertAdjacentHTML('beforeend', '<p data-t-c="gone-a" id="gone-a">a</p><p data-t-c="gone-b" id="gone-b">b</p>'
            + '<button type="button" id="remove-gone" data-on:click="@get(\'/stock\', {target: \'content/gone-a content/gone-b @stock-list\'})">Remove</button>');
        const limit = document.querySelector('#stock-limit');
        if (limit) {
            limit.textContent = 'planted';
        }
    });

    const answered = page.waitForResponse((response) => response.url().endsWith('/stock') && response.request().headers()['tessera-request'] === 'action');
    await page.locator('#remove-gone').click();
    expect((await answered).status()).toBe(200);

    // The DOM shows the answer: a browser may drop the body of a fetch Tessera has read.
    await expect(page.locator('#gone-a')).toHaveCount(0);
    await expect(page.locator('#gone-b')).toHaveCount(0);
    await expect(page.locator('#stock-limit')).toHaveText('The last 10.');
    expect(await sameDocument(page)).toBe(true);
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
