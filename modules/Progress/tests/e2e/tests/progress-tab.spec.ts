import { expect, test } from '@e2e/fixtures';

const COACH_EMAIL = 'e2e-progress-coach@miocoach.test';

test.describe('Progress Tab', () => {
    let clientId: number;

    test.beforeAll(async ({ laravel }) => {
        const result = await laravel.callFunction<{
            coach_email: string;
            client_id: number;
        }>('Modules\\Progress\\Tests\\E2e\\Support\\ProgressFixtures::setup');
        clientId = result.client_id;
    });

    test.beforeEach(async ({ page, laravel }) => {
        const cookie = await laravel.callFunction<{
            name: string;
            value: string;
            domain: string;
            path: string;
        }>('Tests\\Support\\AuthHelper::loginAs', [COACH_EMAIL]);

        await page
            .context()
            .addCookies([{ ...cookie, httpOnly: true, sameSite: 'Lax' }]);
    });

    test('progress tab renders with metric pills and chart', async ({
        page,
    }) => {
        await page.goto(`/coach/clients/${clientId}`);

        // Click Progress tab
        const progressTab = page.locator('[data-testid="tab-progress"]');
        await progressTab.click();

        // Progress tab content loads
        await expect(
            page.locator('[data-testid="progress-tab"]'),
        ).toBeVisible();
        await expect(
            page.locator('[data-testid="metric-pills"]'),
        ).toBeVisible();
        await expect(
            page.locator('[data-testid="range-selector"]'),
        ).toBeVisible();
        await expect(
            page.locator('[data-testid="measurement-chart"]'),
        ).toBeVisible();
    });

    test('selecting a metric pill updates chart', async ({ page }) => {
        await page.goto(`/coach/clients/${clientId}`);

        const progressTab = page.locator('[data-testid="tab-progress"]');
        await progressTab.click();

        await expect(
            page.locator('[data-testid="progress-tab"]'),
        ).toBeVisible();

        // Click body_fat metric pill
        const bodyFatPill = page.locator(
            '[data-testid="metric-pill-body_fat"]',
        );
        await bodyFatPill.click();

        // Chart should still be visible (loads even if no data)
        await expect(
            page.locator('[data-testid="measurement-chart"]'),
        ).toBeVisible();
    });

    test('changing range updates chart', async ({ page }) => {
        await page.goto(`/coach/clients/${clientId}`);

        const progressTab = page.locator('[data-testid="tab-progress"]');
        await progressTab.click();

        await expect(
            page.locator('[data-testid="progress-tab"]'),
        ).toBeVisible();

        // Click 3M range
        const range3M = page.locator('[data-testid="range-3M"]');
        await range3M.click();

        await expect(
            page.locator('[data-testid="measurement-chart"]'),
        ).toBeVisible();
    });

    test('add measurement dialog opens and closes', async ({ page }) => {
        await page.goto(`/coach/clients/${clientId}`);

        const progressTab = page.locator('[data-testid="tab-progress"]');
        await progressTab.click();

        await expect(
            page.locator('[data-testid="progress-tab"]'),
        ).toBeVisible();

        // Open add measurement dialog
        await page.locator('[data-testid="add-measurement-btn"]').click();

        // Dialog visible
        await expect(
            page.locator('[data-testid="measurement-form-dialog"]'),
        ).toBeVisible();

        // Close by pressing Escape
        await page.keyboard.press('Escape');
        await expect(
            page.locator('[data-testid="measurement-form-dialog"]'),
        ).not.toBeVisible();
    });
});
