// aic/gates' one Playwright config (#174): every browser test an app keeps in
// tests/browser/ and every look a workflow run names runs with it, from the
// app's root. gate:23 sets the base URL to the compiled site it serves;
// `vendor/bin/aic-gate browser` sets it to the site a look is taken against.
// The environment carries the rest: AIC_BROWSER_DIR (the tests), AIC_BROWSER_OUTPUT
// (traces and screenshots) and, for a signed-in test, AIC_GATE_LOGIN and
// AIC_GATE_PASSWORD.

import { resolve } from 'node:path';

const env = process.env;

// Playwright reads relative paths from this file's folder, inside aic/gates; the
// tests and their output belong to the app, whose root is the working directory.
const root = process.cwd();

export default {
    testDir: resolve(root, env.AIC_BROWSER_DIR ?? 'tests/browser'),
    testMatch: '**/*.spec.{ts,js,mjs}',
    outputDir: resolve(root, env.AIC_BROWSER_OUTPUT ?? '.native-site/browser'),
    fullyParallel: false,
    workers: 1,
    retries: 0,
    timeout: 30000,
    forbidOnly: true,
    reporter: [['line']],
    use: {
        baseURL: env.AIC_BROWSER_BASE_URL,
        browserName: 'chromium',
        headless: true,
        ignoreHTTPSErrors: true,
        trace: 'retain-on-failure',
    },
};
