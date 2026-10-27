// The stock page through the entity API (#184): a save by SKU is an upsert
// that keeps the date the item first came in, and the list's search, price
// range, SKUs and pages give back what the database holds, with its totals.

import { expect, test, type Page } from '@playwright/test';

const SKU = 'LOOK-184';

const save = async (page: Page, price: string, addedOn: string, quantity: string): Promise<void> => {
    await page.goto('/stock');
    await page.fill('#name', 'Look shelf 184');
    await page.fill('#price', price);
    await page.fill('#added_on', addedOn);
    await page.fill('#sku', SKU);
    await page.fill('#quantity', quantity);
    const posted = page.waitForResponse((response) => response.request().method() === 'POST' && response.url().endsWith('/stock'));
    await page.locator('#stock-form button[type="submit"]').click();
    expect((await posted).status()).toBeLessThan(400);
};

test('a save over a SKU changes the item and keeps the date it first came in', async ({ page }) => {
    await save(page, '51.25', '2026-10-20', '3');
    await save(page, '52.00', '2026-10-27', '4');

    await page.goto(`/stock?sku=${SKU}`);

    await expect(page.locator('#stock-totals')).toHaveText(`1 item, $52.00 in all, the dearest $52.00; SKUs ${SKU}.`);
    await expect(page.locator('#stock li')).toHaveText(['Look shelf 184: $52.00, in stock since 2026-10-20, not certified, 4 on hand']);
});

test('the search, the price range and the pages answer what the database holds', async ({ page }) => {
    await save(page, '52.00', '2026-10-20', '4');

    await page.goto('/stock?q=look+shelf+184&min_price=52&max_price=52');
    await expect(page.locator('#stock-totals')).toHaveText(`1 item, $52.00 in all, the dearest $52.00; SKUs ${SKU}.`);
    await expect(page.locator('#stock li')).toHaveCount(1);

    await page.goto('/stock?q=look+shelf+184&max_price=51.99');
    await expect(page.locator('#stock-totals')).toHaveText('0 items, $0 in all.');

    await page.goto('/stock?q=look+shelf+184&page=2');
    await expect(page.locator('#stock li')).toHaveCount(0);

    await page.goto('/stock?q=%25');
    await expect(page.locator('#stock-totals')).toHaveText('0 items, $0 in all.');
});
