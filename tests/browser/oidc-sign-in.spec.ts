// Sign-in through an OpenID Connect provider (#313): the login page's provider
// button posts to /sign-in/oidc, the browser goes to gate:23's stub provider
// (AIC_UPSTREAM_STUB), which sends it straight back to the callback with a
// code, and the callback signs in as `stub:ada`. A callback this browser did
// not start goes back to the login page, which says why. The id token is
// signed RS256 unless the test asks the stub for an ES256, a tampered or an
// unsigned one first (#343); the last two are refused. All fail on a console
// error, as tessera.spec.ts's do.

import { expect, test, type APIRequestContext } from '@playwright/test';

const stub = process.env.AIC_UPSTREAM_STUB ?? 'http://127.0.0.1:8095';

async function nextToken(request: APIRequestContext, mode: 'es256' | 'tampered' | 'none'): Promise<void> {
    const response = await request.post(stub + '/oidc/next', { form: { mode } });
    expect(response.status()).toBe(204);
}

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

test('the provider button signs in through the stub provider', async ({ page }) => {
    await page.goto('/login');

    await page.locator('#provider-sign-in').click();

    await expect(page).toHaveURL(/\/account$/);
    await expect(page.locator('#signed-in')).toContainText('Signed in as stub:ada (ada@example.test).');
});

test('a callback this browser did not start returns to the login page with the reason', async ({ page }) => {
    await page.goto('/sign-in/oidc/callback?code=forged&state=forged');

    await expect(page).toHaveURL(/\/login$/);
    await expect(page.locator('#sign-in-refused')).toHaveText('This sign-in was not started here, or has finished already.');
});

test('an id token signed ES256 signs in too', async ({ page, request }) => {
    await nextToken(request, 'es256');
    await page.goto('/login');

    await page.locator('#provider-sign-in').click();

    await expect(page).toHaveURL(/\/account$/);
    await expect(page.locator('#signed-in')).toContainText('Signed in as stub:ada (ada@example.test).');
});

test('an id token changed after signing is refused', async ({ page, request }) => {
    await nextToken(request, 'tampered');
    await page.goto('/login');

    await page.locator('#provider-sign-in').click();

    await expect(page).toHaveURL(/\/login$/);
    await expect(page.locator('#sign-in-refused')).toHaveText("The provider's id token was refused: its signature does not verify.");
});

test('an unsigned id token is refused', async ({ page, request }) => {
    await nextToken(request, 'none');
    await page.goto('/login');

    await page.locator('#provider-sign-in').click();

    await expect(page).toHaveURL(/\/login$/);
    await expect(page.locator('#sign-in-refused')).toHaveText("The provider's id token was refused: it is not signed.");
});
