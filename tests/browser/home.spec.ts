// AIC's first kept browser test (#174): the home page lists the demos, and a
// demo link moves through Tessera, which swaps the content region in place
// instead of loading a new document.

import { expect, test } from '@playwright/test';

test('the home page lists the demos', async ({ page }) => {
    await page.goto('/');

    await expect(page.getByRole('heading', { name: 'Demos' })).toBeVisible();
    await expect(page.locator('#demos a[href="/stock"]')).toHaveText('Stock (date, numeric and boolean columns)');
});

test('a demo link swaps the content region without a new document', async ({ page }) => {
    await page.goto('/');
    await page.evaluate(() => {
        (window as Window & { aicSameDocument?: boolean }).aicSameDocument = true;
    });

    await page.locator('#demos a[href="/stock"]').click();

    await expect(page).toHaveURL(/\/stock$/);
    await expect(page.locator('[data-region="content"]').getByRole('heading', { name: 'Stock' })).toBeVisible();
    expect(await page.evaluate(() => (window as Window & { aicSameDocument?: boolean }).aicSameDocument)).toBe(true);
});
