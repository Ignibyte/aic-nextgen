// The OAuth consent page (#331): a person signed in (through gate:23's stub
// provider, as stub:ada, whom the console gives account.audit) opens an MCP
// client's authorization request, reads who asks and what it would hold, and
// allows or denies it. The browser goes back to the client's registered
// redirect URI, which the test answers itself, with a code or the error and
// the client's state. All fail on a console error, as tessera.spec.ts's do.

import { expect, test, type Page } from '@playwright/test';

const callback = 'http://127.0.0.1:7777/callback';

const authorize = '/oauth/authorize?' + new URLSearchParams({
    response_type: 'code',
    client_id: 'gate-desk',
    redirect_uri: callback,
    scope: 'account.audit ledger.mcp',
    state: 's1',
    code_challenge: 'OekZVEnXsRH8TiRdRr-KPbTynr0gqxNTfKHLLvy5b6M',
    code_challenge_method: 'S256',
}).toString();

const errors: string[] = [];

async function consent(page: Page): Promise<void> {
    await page.route(callback + '**', (route) => route.fulfill({ status: 200, contentType: 'text/plain', body: 'client' }));
    await page.goto('/login');
    await page.locator('#provider-sign-in').click();
    await expect(page).toHaveURL(/\/account$/);
    await page.goto(authorize);
    await expect(page.getByRole('heading', { name: 'Allow gate-desk?' })).toBeVisible();
    await expect(page.locator('#consent-scopes')).toHaveText('It would act for you with account.audit.');
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

test('allowing sends the client a code and its state', async ({ page }) => {
    await consent(page);

    await page.locator('#consent-allow').click();

    await expect(page).toHaveURL(/^http:\/\/127\.0\.0\.1:7777\/callback\?code=[0-9a-f]{64}&state=s1$/);
});

test('denying sends the client access_denied and its state', async ({ page }) => {
    await consent(page);

    await page.locator('#consent-deny').click();

    await expect(page).toHaveURL(callback + '?error=access_denied&state=s1');
});
