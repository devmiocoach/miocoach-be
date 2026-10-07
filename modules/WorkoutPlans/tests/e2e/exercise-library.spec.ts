import { expect } from '@playwright/test';
import { test } from '../../../../tests/e2e/fixtures';
import { loginAs } from '../../../../tests/e2e/helpers/auth';

const COACH = { email: 'coach@miocoach.dev', password: 'secretsauce' };

test.describe('Exercise library', () => {
    test.use({ viewport: { width: 1440, height: 900 } });

    test.beforeEach(async ({ page, laravel }) => {
        await laravel.callFunction('Tests\\Support\\AuthHelper::ensureCoach', [
            COACH.email,
        ]);
        await loginAs(page, laravel, COACH);
        await page.goto('/coach/exercises');
    });

    test('the plans page links to the exercise library', async ({ page }) => {
        await page.goto('/coach/plans');
        await page.getByTestId('stat-exercises').click();

        await expect(page).toHaveURL(/\/coach\/exercises$/);
        await expect(page.getByTestId('new-exercise-btn')).toBeVisible();
    });

    test('search filters the grid', async ({ page }) => {
        const before = await page
            .getByTestId('library-list')
            .locator('[data-testid^="exercise-card-"]')
            .count();

        await page.getByTestId('library-search').fill('zzzz-nothing-matches');

        await expect(page.getByTestId('library-empty')).toBeVisible();
        expect(before).toBeGreaterThan(0);
    });

    test('a disallowed video host shows a validation error', async ({
        page,
    }) => {
        await page.getByTestId('new-exercise-btn').click();
        await page.getByTestId('exercise-form-name').fill('Sospetto');
        await page
            .getByTestId('exercise-form-video')
            .fill('https://evil.example.com/v.mp4');
        await page.getByTestId('exercise-form-submit').click();

        await expect(
            page.getByTestId('exercise-form-video-error'),
        ).toBeVisible();
    });

    test('a coach cannot edit a global exercise', async ({ page }) => {
        // Global cards render no actions menu at all.
        const globalCard = page
            .getByTestId('library-list')
            .locator('[data-testid^="exercise-card-"]')
            .first();

        await expect(
            globalCard.locator('[data-testid^="exercise-actions-"]'),
        ).toHaveCount(0);
    });
});
