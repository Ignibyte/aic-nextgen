// A queued job, worked by a look (#225): the look runs the app's queue
// worker itself, `bin/aic queue:work`, with the PHP and the database the
// site under test uses (gate:23 and `aic-gate browser` hand it `AIC_PHP` and
// the site's PG* settings), so a job's effect is seen on either site.

import { execFileSync } from 'node:child_process';
import { expect, test } from '@playwright/test';

function work(): string {
    return execFileSync(process.env.AIC_PHP ?? 'php', ['bin/aic', 'queue:work'], { env: process.env, encoding: 'utf8' });
}

test('a queued job waits until the worker runs it', async ({ page }) => {
    work();
    await page.goto('/jobs');
    const fresh = page.locator('#waiting li', { hasText: 'attempts 0' });
    await expect(fresh).toHaveCount(0);

    await page.getByRole('button', { name: 'Queue a job (retries once, then succeeds)' }).click();
    await expect(page).toHaveURL(/\/jobs\?queued=succeed$/);
    await expect(fresh).toHaveCount(1);

    expect(work()).toMatch(/^queue:work ran [1-9][0-9]* job\(s\)\./);
    await page.reload();
    await expect(fresh).toHaveCount(0);
});
