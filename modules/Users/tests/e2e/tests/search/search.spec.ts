import { expect, test } from '@e2e/fixtures';
import type { Page } from '@playwright/test';

test.describe.configure({ timeout: 120_000 });

const expectSlow = expect.configure({ timeout: 20_000 });

/**
 * Search page filters (desktop sidebar). Fixture createSearchCoaches
 * publishes exactly 3 coaches that differ on every dimension:
 *
 *   Sara Bianchi  — Yoga, Milano, in_person, 30€, F, Lunedì/Mercoledì mattina, ★ 5.0
 *   Marco Rossi   — Crossfit/HIIT, Roma, online, 80€, M, Martedì/Giovedì pomeriggio, ★ 4.0
 *   Giulia Verdi  — Pilates, Milano, hybrid, 50€, F, Venerdì sera, ★ 4.5
 */
test.describe('Coach Search', () => {
    test.beforeEach(async ({ laravel }) => {
        await laravel.callFunction(
            'Modules\\Users\\Tests\\E2e\\Support\\CoachFixtures::createSearchCoaches',
        );
    });

    async function setPrice(page: Page, min: number, max: number) {
        const ranges = page.locator('input[type="range"]');
        await ranges.nth(0).evaluate((el, value) => {
            (el as HTMLInputElement).value = String(value);
            el.dispatchEvent(new Event('input', { bubbles: true }));
        }, min);
        await ranges.nth(1).evaluate((el, value) => {
            (el as HTMLInputElement).value = String(value);
            el.dispatchEvent(new Event('input', { bubbles: true }));
        }, max);
    }

    test('lists the published coaches on initial load', async ({ page }) => {
        await page.goto('/search');
        await expectSlow(page.getByText('3 coach trovati')).toBeVisible();
        await expectSlow(page.getByTestId('coach-card')).toHaveCount(3);
        await expectSlow(page).toHaveURL(/\/search$/);
    });

    test('text search by name narrows the results without navigating', async ({
        page,
    }) => {
        await page.goto('/search');
        await page.getByTestId('search-specialization').fill('Sara');
        await page.getByTestId('search-specialization').press('Enter');

        await expectSlow(page.getByTestId('coach-card')).toHaveCount(1);
        await expectSlow(
            page.getByTestId('coach-card').filter({ hasText: 'Sara Bianchi' }),
        ).toBeVisible();
        await expectSlow(page).toHaveURL(/\/search$/);
    });

    test('text search by specialization name narrows the results', async ({
        page,
    }) => {
        await page.goto('/search');
        await page.getByTestId('search-specialization').fill('Yoga');
        await page.getByTestId('search-specialization').press('Enter');

        await expectSlow(page.getByTestId('coach-card')).toHaveCount(1);
        await expectSlow(
            page.getByTestId('coach-card').filter({ hasText: 'Sara Bianchi' }),
        ).toBeVisible();
    });

    test('city search narrows the results', async ({ page }) => {
        await page.goto('/search');
        await page.getByTestId('search-city').fill('Milano');
        await page.getByTestId('search-city').press('Enter');

        await expectSlow(page.getByTestId('coach-card')).toHaveCount(2);
        await expectSlow(
            page.getByTestId('coach-card').filter({ hasText: 'Sara Bianchi' }),
        ).toBeVisible();
        await expectSlow(
            page.getByTestId('coach-card').filter({ hasText: 'Giulia Verdi' }),
        ).toBeVisible();
    });

    test('mode filter keeps only matching coaches', async ({ page }) => {
        await page.goto('/search');
        await page.getByLabel('Online', { exact: true }).check();
        await expectSlow(page.getByTestId('coach-card')).toHaveCount(1);
        await expectSlow(
            page.getByTestId('coach-card').filter({ hasText: 'Marco Rossi' }),
        ).toBeVisible();
    });

    test('price filter narrows the results', async ({ page }) => {
        await page.goto('/search');
        await setPrice(page, 30, 45);
        await expectSlow(page.getByTestId('coach-card')).toHaveCount(1);
        await expectSlow(
            page.getByTestId('coach-card').filter({ hasText: 'Sara Bianchi' }),
        ).toBeVisible();
    });

    test('single specialization filter narrows the results', async ({
        page,
    }) => {
        await page.goto('/search');
        await page.getByLabel('Yoga', { exact: true }).check();
        await expectSlow(page.getByTestId('coach-card')).toHaveCount(1);
        await expectSlow(
            page.getByTestId('coach-card').filter({ hasText: 'Sara Bianchi' }),
        ).toBeVisible();
        await expectSlow(page).toHaveURL(/\/search$/);
    });

    test('two specialization filters return the union', async ({ page }) => {
        await page.goto('/search');
        await page.getByLabel('Yoga', { exact: true }).check();
        await page.getByLabel('Pilates', { exact: true }).check();
        await expectSlow(page.getByTestId('coach-card')).toHaveCount(2);
        await expectSlow(
            page.getByTestId('coach-card').filter({ hasText: 'Sara Bianchi' }),
        ).toBeVisible();
        await expectSlow(
            page.getByTestId('coach-card').filter({ hasText: 'Giulia Verdi' }),
        ).toBeVisible();
    });

    test('gender filter narrows the results', async ({ page }) => {
        await page.goto('/search');
        await page.getByRole('button', { name: 'Donna', exact: true }).click();
        await expectSlow(page.getByTestId('coach-card')).toHaveCount(2);
    });

    test('rating filter keeps only coaches above the threshold', async ({
        page,
    }) => {
        await page.goto('/search');
        await page.getByLabel('4.5+ ★', { exact: true }).check();
        await expectSlow(page.getByTestId('coach-card')).toHaveCount(2);
        await expectSlow(
            page.getByTestId('coach-card').filter({ hasText: 'Sara Bianchi' }),
        ).toBeVisible();
        await expectSlow(
            page.getByTestId('coach-card').filter({ hasText: 'Giulia Verdi' }),
        ).toBeVisible();
    });

    test('availability day filter narrows the results', async ({ page }) => {
        await page.goto('/search');
        await page
            .getByRole('button', { name: 'L', exact: true })
            .first()
            .click();
        await expectSlow(page.getByTestId('coach-card')).toHaveCount(1);
        await expectSlow(
            page.getByTestId('coach-card').filter({ hasText: 'Sara Bianchi' }),
        ).toBeVisible();
    });

    test('time slot filter narrows the results', async ({ page }) => {
        await page.goto('/search');
        await page.getByRole('button', { name: 'Sera', exact: true }).click();
        await expectSlow(page.getByTestId('coach-card')).toHaveCount(1);
        await expectSlow(
            page.getByTestId('coach-card').filter({ hasText: 'Giulia Verdi' }),
        ).toBeVisible();
    });

    test('each sort orders the results', async ({ page }) => {
        await page.goto('/search');
        const sort = page.locator('select');

        await sort.selectOption('price_asc');
        await expectSlow(
            page
                .getByTestId('coach-card')
                .first()
                .filter({ hasText: 'Sara Bianchi' }),
        ).toBeVisible();

        await sort.selectOption('price_desc');
        await expectSlow(
            page
                .getByTestId('coach-card')
                .first()
                .filter({ hasText: 'Marco Rossi' }),
        ).toBeVisible();

        await sort.selectOption('rating_desc');
        await expectSlow(
            page
                .getByTestId('coach-card')
                .first()
                .filter({ hasText: 'Sara Bianchi' }),
        ).toBeVisible();
    });

    test('chips list active filters and can be removed individually', async ({
        page,
    }) => {
        await page.goto('/search');
        await page.getByLabel('Yoga', { exact: true }).check();

        await expectSlow(page.getByTestId('chip-spec-yoga')).toBeVisible();

        await page.getByTestId('chip-spec-yoga').click();
        await expectSlow(page.getByTestId('chip-spec-yoga')).toHaveCount(0);
        await expectSlow(page.getByTestId('coach-card')).toHaveCount(3);
    });

    test('reset filters clears the sidebar but keeps the text search', async ({
        page,
    }) => {
        await page.goto('/search');
        await page.getByTestId('search-specialization').fill('Sara');
        await page.getByTestId('search-specialization').press('Enter');
        await page.getByLabel('Yoga', { exact: true }).check();

        await expectSlow(page.getByTestId('coach-card')).toHaveCount(1);
        await page.getByTestId('reset-filters').click();

        await expectSlow(page.getByTestId('active-filter-chips')).toHaveCount(
            0,
        );
        await expectSlow(page.getByTestId('coach-card')).toHaveCount(1);
        await expectSlow(
            page.getByTestId('coach-card').filter({ hasText: 'Sara Bianchi' }),
        ).toBeVisible();
    });

    test('empty state offers to reset the active filters', async ({ page }) => {
        await page.goto('/search');
        await page.getByLabel('Crossfit', { exact: true }).check();
        await page.getByRole('button', { name: 'Donna', exact: true }).click();

        await expectSlow(page.getByText('Nessun coach trovato')).toBeVisible();
        await expectSlow(page.getByTestId('reset-filters-empty')).toBeVisible();

        await page.getByTestId('reset-filters-empty').click();
        await expectSlow(page.getByTestId('coach-card')).toHaveCount(3);
    });
});

test.describe('Coach Search — infinite scroll', () => {
    test.beforeEach(async ({ laravel }) => {
        await laravel.callFunction(
            'Modules\\Users\\Tests\\E2e\\Support\\CoachFixtures::createScrollCoaches',
        );
    });

    test('loads more results when reaching the sentinel', async ({ page }) => {
        await page.goto('/search');

        await expectSlow(page.getByTestId('coach-card')).toHaveCount(20);

        await page.getByTestId('search-sentinel').scrollIntoViewIfNeeded();

        await expectSlow(page.getByTestId('coach-card')).toHaveCount(25);
    });
});
