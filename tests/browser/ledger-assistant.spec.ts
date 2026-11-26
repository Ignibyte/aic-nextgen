// The ledger's in-app assistant (#332): a person signed in (through gate:23's
// stub provider, as stub:ada, whom the console gives ledger.split) reads what
// the assistant may call under its ceiling, previews a split as a dry run,
// splits, and sends the same call again, which answers the first call's result
// without running it. All fail on a console error, as tessera.spec.ts's do.

import { expect, test, type Page } from '@playwright/test';

const parts = '"parts":[{"cents":34,"index":0,"label":"part 1"},{"cents":33,"index":1,"label":"part 2"},{"cents":33,"index":2,"label":"part 3"}]';

const errors: string[] = [];

async function assistant(page: Page): Promise<void> {
    await page.goto('/login');
    await page.locator('#provider-sign-in').click();
    await expect(page).toHaveURL(/\/account$/);
    await page.goto('/ledger/assistant');
    await expect(page.getByRole('heading', { name: 'Ledger assistant', exact: true })).toBeVisible();
    await expect(page.getByRole('row', { name: /ledger\.split .* yes/ })).toBeVisible();
    await page.locator('#assistant-amount').fill('100');
    await page.locator('#assistant-parts').fill('3');
}

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

test('a preview answers the split as a dry run', async ({ page }) => {
    await assistant(page);

    await page.locator('#assistant-dry').check();
    await page.locator('#assistant-split').click();

    await expect(page.locator('#assistant-result')).toHaveText('A dry run: nothing was kept.');
    await expect(page.locator('code')).toContainText(parts);
});

test('the same call sent again answers the first result', async ({ page }) => {
    await assistant(page);

    await page.locator('#assistant-split').click();
    await expect(page.locator('#assistant-result')).toHaveText('Split. The call is kept under its id.');

    await page.locator('#assistant-again').click();

    await expect(page.locator('#assistant-result')).toHaveText(/^Replayed: /);
    await expect(page.locator('code')).toContainText(parts);
});
