// A page kept by a job (#265): the kept-page job renders a page in the theme
// and keeps it as a private file, and GET /kept-page sends it inline. The look
// runs the queue worker itself, as the jobs look does, and opens the kept page
// from the jobs page's link to see it in the site's styles.

import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';

function work(): string {
    return execFileSync(process.env.AIC_PHP ?? 'php', ['bin/aic', 'queue:work'], { env: process.env, encoding: 'utf8' });
}

test('a page a job keeps opens from the site in its theme', async ({ page }) => {
    await page.goto('/jobs');
    await page.getByRole('button', { name: 'Keep a page' }).click();
    await expect(page).toHaveURL(/\/jobs\?queued=page$/);
    expect(work()).toMatch(/^queue:work ran [1-9][0-9]* job\(s\)\./);

    await page.getByRole('link', { name: 'The kept page' }).click();
    await expect(page).toHaveURL(/\/kept-page$/);
    await expect(page).toHaveTitle('Kept page · AIC nextgen');
    await expect(page.getByRole('heading', { name: 'A kept page' })).toBeVisible();
    await expect(page.locator('#kept li')).toHaveText('Rendered by a job, in the theme, and kept as a private file.');

    // The canonical stylesheet is immutable, so it may come from the cache /jobs filled: count its rules.
    await expect.poll(() => page.evaluate(() => document.styleSheets[0]?.cssRules.length ?? 0)).toBeGreaterThan(0);
});
