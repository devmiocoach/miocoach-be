import { expect } from '@playwright/test';

import { test } from '../../../../tests/e2e/fixtures';
import { loginAs } from '../../../../tests/e2e/helpers/auth';

const COACH = { email: 'coach@miocoach.dev', password: 'secretsauce' };

test.describe('Plan builder', () => {
    test.use({ viewport: { width: 1440, height: 900 } });

    test.beforeEach(async ({ page, laravel }) => {
        await laravel.callFunction('Tests\\Support\\AuthHelper::ensureCoach', [
            COACH.email,
        ]);
        await loginAs(page, laravel, COACH);
        await page.goto('/coach/plans/create');
    });

    test('adding an exercise with the + button creates exactly one card', async ({
        page,
    }) => {
        await page.getByTestId('add-day-btn').click();

        const firstCard = page
            .getByTestId('library-list')
            .locator('[data-testid^="add-exercise-"]')
            .first();
        await firstCard.click();

        await expect(
            page
                .getByTestId('day-exercise-list')
                .locator('[data-testid^="plan-exercise-"]'),
        ).toHaveCount(1);
    });

    test('dragging from the library into a day creates exactly one card and no ghost', async ({
        page,
    }) => {
        await page.getByTestId('add-day-btn').click();

        const source = page
            .getByTestId('library-list')
            .locator('[data-testid^="exercise-card-"]')
            .first();
        const target = page.getByTestId('day-exercise-list');

        await source.dragTo(target);

        await expect(
            target.locator('[data-testid^="plan-exercise-"]'),
        ).toHaveCount(1);
        // The library keeps its card: the drag clones, it does not move.
        await expect(
            page
                .getByTestId('library-list')
                .locator('[data-testid^="exercise-card-"]')
                .first(),
        ).toBeVisible();
    });

    test('a new exercise created from the builder lands in the library', async ({
        page,
    }) => {
        const name = `Builder Custom ${Date.now()}`;

        await page.getByTestId('builder-new-exercise-btn').click();
        await page.getByTestId('exercise-form-name').fill(name);
        await page.getByTestId('exercise-form-submit').click();

        await expect(page.getByTestId('exercise-form-modal')).toBeHidden();
        await page.getByTestId('library-search').fill(name);
        await expect(
            page
                .getByTestId('library-list')
                .locator('[data-testid^="exercise-card-"]'),
        ).toHaveCount(1);
    });

    test('the save badge flips from Draft to Saved', async ({ page }) => {
        await page.getByTestId('field-plan-name').fill('Piano E2E');
        await expect(page.getByTestId('save-badge')).toHaveText(/Bozza|Draft/);

        await page.getByTestId('save-plan-btn').click();

        await expect(page.getByTestId('save-badge')).toHaveText(
            /Salvato|Saved/,
            { timeout: 5_000 },
        );
    });

    test('reordering with the arrow buttons persists across a reload', async ({
        page,
    }) => {
        await page.getByTestId('add-day-btn').click();

        const addButtons = page
            .getByTestId('library-list')
            .locator('[data-testid^="add-exercise-"]');
        await addButtons.nth(0).click();
        await addButtons.nth(1).click();

        await page.getByTestId('field-plan-name').fill('Ordine');
        await page.getByTestId('save-plan-btn').click();
        await expect(page.getByTestId('save-badge')).toHaveText(
            /Salvato|Saved/,
        );

        const firstName = await page
            .getByTestId('plan-exercise-0')
            .locator('p')
            .first()
            .innerText();

        await page.getByTestId('move-down-0').click();
        await page.getByTestId('save-plan-btn').click();
        await expect(page.getByTestId('save-badge')).toHaveText(
            /Salvato|Saved/,
        );

        await page.reload();

        await expect(
            page.getByTestId('plan-exercise-1').locator('p').first(),
        ).toHaveText(firstName);
    });

    test('editing an inline field and blurring saves immediately', async ({
        page,
    }) => {
        await page.getByTestId('add-day-btn').click();
        await page
            .getByTestId('library-list')
            .locator('[data-testid^="add-exercise-"]')
            .first()
            .click();
        await page.getByTestId('field-plan-name').fill('Blur');
        await page.getByTestId('save-plan-btn').click();
        await expect(page.getByTestId('save-badge')).toHaveText(
            /Salvato|Saved/,
        );

        await page.getByTestId('field-sets-0').fill('5');
        await page.getByTestId('field-sets-0').blur();

        await expect(page.getByTestId('save-badge')).toHaveText(
            /Salvato|Saved/,
            { timeout: 5_000 },
        );

        await page.reload();
        await expect(page.getByTestId('field-sets-0')).toHaveValue('5');
    });
});
