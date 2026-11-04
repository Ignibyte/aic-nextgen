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

// The queue's records (#262): the page reads the queue through AIC's
// QueueRecords, so a job tried once and released shows as retrying, and a
// failed job can be discarded from the failed record.
// The succeed mode retries once, so the job leaves no failed record behind:
// gate:23's two passes share one database, and a failing job reaching the
// record between them would make the passes differ.
test('a job tried once and released shows as retrying', async ({ page }) => {
    await page.goto('/jobs');
    await page.getByRole('button', { name: 'Queue a job (retries once, then succeeds)' }).click();
    await expect(page).toHaveURL(/\/jobs\?queued=succeed$/);
    work();
    await page.reload();
    const retrying = page.locator('#waiting li', { hasText: /\{"mode":"succeed"\} \(retrying\), attempts 1, due \d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/ });
    await expect(retrying.first()).toBeVisible();
});

test('a failed job is discarded from the failed record', async ({ page }) => {
    await page.goto('/jobs');
    await page.getByRole('button', { name: 'Queue a job that fails at once' }).click();
    await expect(page).toHaveURL(/\/jobs\?queued=broken$/);
    work();
    await page.reload();
    const newest = page.locator('#failed li').first();
    await expect(newest).toHaveText(/^#\d+ demo after 1 attempts: The demo job was told to fail\., failed \d{4}-/);
    const id = ((await newest.textContent()) ?? '').match(/^#(\d+)/)?.[1] ?? '';

    await page.getByRole('button', { name: `Discard #${id}` }).click();
    await expect(page).toHaveURL(new RegExp(`/jobs\\?discarded=${id}$`));
    await expect(page.locator('#failed li', { hasText: `#${id} ` })).toHaveCount(0);
    await expect(page.getByRole('button', { name: `Discard #${id}` })).toHaveCount(0);
});
