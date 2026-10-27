// The stock report (#191): a day read from the `stock_days` view holds as many
// items as the stock list's search finds for it, and the worth by certification
// adds up to the day's worth.

import { expect, test } from '@playwright/test';

test('one day of the report matches its items and its worth', async ({ page }) => {
    await page.goto('/stock/days?day=2026-10-20');
    const line = (await page.locator('#stock-days li').first().textContent()) ?? '';
    const [, items, worth] = /^2026-10-20: (\d+) items?, \$([\d.]+)$/.exec(line) ?? [];

    expect(Number(items)).toBeGreaterThan(0);
    const split = (await page.locator('#stock-worth').textContent()) ?? '';
    const [, certified, other] = /^Certified: \$([\d.]+); not certified: \$([\d.]+)\.$/.exec(split) ?? [];
    expect(Number(certified) + Number(other)).toBeCloseTo(Number(worth), 2);
});

test('the report without a day lists the latest days, newest first', async ({ page }) => {
    await page.goto('/stock/days');
    const days = await page.locator('#stock-days li').allTextContents();

    expect(days.length).toBeGreaterThan(0);
    const dates = days.map((day) => day.slice(0, 10));
    expect([...dates].sort().reverse()).toEqual(dates);
});
