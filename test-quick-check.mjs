import { chromium } from 'playwright';
const CHROME = '/Users/Samuele/Library/Caches/ms-playwright/chromium-1217/chrome-mac-arm64/Google Chrome for Testing.app/Contents/MacOS/Google Chrome for Testing';
const BASE = 'https://miocoach.test';

const br = await chromium.launch({ executablePath: CHROME, headless: true });
const ctx = await br.newContext({ ignoreHTTPSErrors: true });
const page = await ctx.newPage();

await page.goto(BASE + '/auth/login');
await page.waitForLoadState('networkidle');
await page.getByText('Sono un Coach').click();
await page.waitForTimeout(300);
await page.fill('[data-testid="email"]', 'testcoach@miocoach.test');
await page.fill('[data-testid="password"]', 'password');
await page.click('[data-testid="login-button"]');
await page.waitForLoadState('networkidle');

await page.goto(BASE + '/coach/onboarding?step=2');
await page.waitForLoadState('networkidle');
await page.waitForTimeout(1000);
await page.screenshot({ path: '.playwright-mcp/step2-direct-nav.png', fullPage: true });

const h1 = await page.locator('h1').first().textContent().catch(() => 'none');
console.log('H1:', h1?.trim());
const url = page.url();
console.log('URL:', url);
// Check Inertia props initialStep
const props = await page.evaluate(() => {
    const el = document.getElementById('app');
    if (!el) return null;
    const data = el.getAttribute('data-page');
    return data ? JSON.parse(data).props : null;
});
console.log('initialStep prop:', props?.initialStep);

await br.close();
