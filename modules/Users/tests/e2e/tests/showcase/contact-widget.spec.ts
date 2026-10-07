import { expect, test } from '@e2e/fixtures';

test.describe.configure({ timeout: 120_000 });

const expectSlow = expect.configure({ timeout: 20_000 });

type Slugs = Record<string, string>;

/**
 * Contact widget on the public coach profile page. Fixture createShowcaseCoaches
 * publishes 3 coaches that cover every contact-preferences combination:
 *
 *   Ana Moretti — only direct booking
 *   Leo Fontana — only direct message
 *   Nina Gallo  — booking + free consultation + direct message
 */
test.describe('Coach contact widget', () => {
    let slugs: Slugs;

    test.beforeEach(async ({ laravel }) => {
        await laravel.callFunction(
            'Modules\\Users\\Tests\\E2e\\Support\\CoachFixtures::createShowcaseCoaches',
        );
        await laravel.callFunction(
            'Modules\\Users\\Tests\\E2e\\Support\\CoachFixtures::createShowcaseClient',
        );

        slugs = await laravel.callFunction<Slugs>(
            'Modules\\Users\\Tests\\E2e\\Support\\CoachFixtures::showcaseSlugs',
        );
    });

    async function gotoProfile(
        page: import('@playwright/test').Page,
        coach: keyof typeof slugs,
    ) {
        await page.goto(`/coach/${slugs[coach]}`, {
            waitUntil: 'networkidle',
        });
        await expectSlow(page.getByTestId('contact-widget')).toBeVisible();
    }

    test('booking-only coach renders a single booking CTA', async ({
        page,
    }) => {
        await gotoProfile(page, 'e2e-showcase-booking@miocoach.test');

        await expectSlow(page.getByTestId('contact-cta-booking')).toBeVisible();
        await expect(
            page.getByTestId('contact-cta-free_consultation'),
        ).toHaveCount(0);
        await expect(page.getByTestId('contact-cta-message')).toHaveCount(0);
    });

    test('message-only coach renders a single message CTA', async ({
        page,
    }) => {
        await gotoProfile(page, 'e2e-showcase-message@miocoach.test');

        await expectSlow(page.getByTestId('contact-cta-message')).toBeVisible();
        await expect(page.getByTestId('contact-cta-booking')).toHaveCount(0);
        await expect(
            page.getByTestId('contact-cta-free_consultation'),
        ).toHaveCount(0);
    });

    test('coach with all channels renders primary booking plus secondary CTAs', async ({
        page,
    }) => {
        await gotoProfile(page, 'e2e-showcase-all@miocoach.test');

        await expectSlow(page.getByTestId('contact-cta-booking')).toBeVisible();
        await expectSlow(
            page.getByTestId('contact-cta-free_consultation'),
        ).toBeVisible();
        await expectSlow(page.getByTestId('contact-cta-message')).toBeVisible();
    });

    test('guest clicking a message CTA is sent to the login page', async ({
        page,
    }) => {
        await page.context().clearCookies();
        await gotoProfile(page, 'e2e-showcase-message@miocoach.test');

        await page.getByTestId('contact-cta-message').click();

        await expect(page).toHaveURL(/auth\/login/);
        await expect(page.getByTestId('login-form')).toBeVisible();
    });

    test('guest clicking the booking CTA is sent to the login page', async ({
        page,
    }) => {
        await page.context().clearCookies();
        await gotoProfile(page, 'e2e-showcase-all@miocoach.test');

        await page.getByTestId('contact-cta-booking').click();

        await expect(page).toHaveURL(/auth\/login/);
        await expect(page.getByTestId('login-form')).toBeVisible();
    });

    test('client sends a message from the dialog and sees the success toast', async ({
        page,
        loginAs,
    }) => {
        await loginAs({
            email: 'e2e-client@miocoach.test',
            password: 'secretsauce',
        });

        await gotoProfile(page, 'e2e-showcase-message@miocoach.test');

        await page.getByTestId('contact-cta-message').click();
        await expect(page.getByTestId('contact-message')).toBeVisible();
        await page
            .getByTestId('contact-message')
            .fill('Ciao Leo, vorrei iniziare un percorso di allenamento!');
        await page.getByTestId('contact-submit').click();

        await expect(
            page.getByText(
                'Richiesta inviata! Il coach ti risponderà a breve.',
            ),
        ).toBeVisible();
        await expect(page.getByTestId('contact-message')).toHaveCount(0);
    });

    test('client cannot submit a message without text', async ({
        page,
        loginAs,
    }) => {
        await loginAs({
            email: 'e2e-client@miocoach.test',
            password: 'secretsauce',
        });

        await gotoProfile(page, 'e2e-showcase-message@miocoach.test');

        await page.getByTestId('contact-cta-message').click();
        await expect(page.getByTestId('contact-message')).toBeVisible();

        await page.getByTestId('contact-message').fill('');
        await page.getByTestId('contact-submit').click();

        // Native `required` validation blocks the submit: dialog stays open and
        // no success toast is shown.
        await expect(page.getByTestId('contact-message')).toBeVisible();
        await expect(
            page.getByText(
                'Richiesta inviata! Il coach ti risponderà a breve.',
            ),
        ).toHaveCount(0);
    });

    test('coach user sees disabled CTAs instead of contact links', async ({
        page,
        laravel,
    }) => {
        // Login as the e2e coach (role: coach) from the onboarding fixtures.
        await laravel.callFunction(
            'Modules\\Users\\Tests\\E2e\\Support\\CoachFixtures::createIncompleteCoach',
        );
        const cookie = await laravel.callFunction<{
            name: string;
            value: string;
            domain: string;
            path: string;
        }>('Tests\\Support\\AuthHelper::loginAs', ['e2e-coach@miocoach.test']);
        await page
            .context()
            .addCookies([{ ...cookie, httpOnly: true, sameSite: 'Lax' }]);

        await gotoProfile(page, 'e2e-showcase-all@miocoach.test');

        const bookingCta = page.getByTestId('contact-cta-booking');
        await expectSlow(bookingCta).toBeVisible();
        await expect(bookingCta).toBeDisabled();
        await expect(bookingCta).toHaveAttribute(
            'title',
            'Accedi come cliente per contattare il coach',
        );
    });
});
