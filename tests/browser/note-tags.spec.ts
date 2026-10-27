// The note module's tags page (#190): a tag added to a note is listed with the
// note's title, which the page reads through a join on the tag's reference.

import { expect, test } from '@playwright/test';

test('a tag added to a note shows beside its title, and the search finds it by the title', async ({ page }) => {
    await page.goto('/notes');
    const link = page.locator('#notes a').first();
    const title = (await link.textContent())?.trim() ?? '';
    const id = (await link.getAttribute('href'))?.split('/').pop() ?? '';

    await page.goto('/notes/tags');
    await page.fill('#note_id', id);
    await page.fill('#tag', 'look-190');
    const posted = page.waitForResponse((response) => response.request().method() === 'POST' && response.url().endsWith('/notes/tags'));
    await page.locator('#tag-form button[type="submit"]').click();
    expect((await posted).status()).toBeLessThan(400);

    await page.goto('/notes/tags?q=' + encodeURIComponent(title));
    await expect(page.locator('#tags li', { hasText: `look-190: ${title} (#${id})` })).toHaveCount(1);
});

test('a tag for a note that does not exist is refused beside the form', async ({ page }) => {
    await page.goto('/notes/tags');
    await page.fill('#note_id', '999999999');
    await page.fill('#tag', 'look-190');
    await page.locator('#tag-form button[type="submit"]').click();

    await expect(page.locator('#tag-error')).toHaveText('No note has that number.');
});
