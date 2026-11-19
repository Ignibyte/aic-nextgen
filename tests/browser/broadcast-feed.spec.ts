// Real-time push (#314): two pages of the ledger feed, signed in through
// gate:23's stub provider as `stub:ada`, whom the gate's console gives the
// `ledger.feed` role. A post on one page reaches the other through its
// broadcast stream, and the posting page keeps it too (the list is
// `data-permanent`, so the post's redirect does not morph it away); it still
// does after the stream's 3-second hold has ended a connection and Tessera
// has reconnected. Each post carries a fresh word, since the gate's
// database keeps the posts of earlier runs. It fails on a console error, as
// tessera.spec.ts's tests do.

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

async function openFeed(page: Page): Promise<void> {
    const streaming = page.waitForResponse((response) => response.url().includes('/ledger/feed/events'));
    await page.goto('/ledger/feed');
    await streaming;
}

async function post(page: Page, text: string): Promise<void> {
    await page.locator('#feed-text').fill(text);
    await page.locator('#feed-post').click();
}

test('a post on one page appears on another, before and after a reconnect', async ({ page, context }) => {
    await page.goto('/login');
    await page.locator('#provider-sign-in').click();
    await expect(page).toHaveURL(/\/account$/);

    await openFeed(page);
    const other = await context.newPage();
    await openFeed(other);

    const first = `first ${Date.now()}`;
    await post(other, first);
    await expect(page.locator('#feed li', { hasText: first })).toHaveCount(1, { timeout: 10_000 });
    await expect(other.locator('#feed li', { hasText: first })).toHaveCount(1, { timeout: 10_000 });

    await page.waitForTimeout(4_000);

    const second = `second ${Date.now()}`;
    await post(other, second);
    await expect(page.locator('#feed li', { hasText: second })).toHaveCount(1, { timeout: 10_000 });
    await expect(page.locator('#feed li', { hasText: first })).toHaveCount(1);

    await other.close();
});
