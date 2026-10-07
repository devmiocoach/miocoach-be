import { expect, test } from '@e2e/fixtures';
import { OnboardingPage } from '../../pages/OnboardingPage';

const COACH_EMAIL = 'e2e-coach@miocoach.test';

/**
 * Wizard order: 1 Profilo · 2 Specialità · 3 Certificazioni · 4 Disponibilità
 * · 5 Sede · 6 Tariffe · 7 Gallery · 8 Policy · 9 Revisione.
 * Steps 3, 5, 7 and 8 are optional and can be skipped.
 */
test.describe('Coach Onboarding Wizard', () => {
    let onboarding: OnboardingPage;

    test.beforeEach(async ({ page, laravel }) => {
        // Reset coach to incomplete onboarding state before each test
        await laravel.callFunction(
            'Modules\\Users\\Tests\\E2e\\Support\\CoachFixtures::createIncompleteCoach',
        );

        // Log in as the coach via session cookie (no UI login needed)
        const cookie = await laravel.callFunction<{
            name: string;
            value: string;
            domain: string;
            path: string;
        }>('Tests\\Support\\AuthHelper::loginAs', [COACH_EMAIL]);

        await page
            .context()
            .addCookies([{ ...cookie, httpOnly: true, sameSite: 'Lax' }]);

        onboarding = new OnboardingPage(page);
    });

    /** Steps 1 → 2 → (skip 3) → 4, leaving the wizard on Disponibilità. */
    async function reachAvailability() {
        await onboarding.goto();
        await onboarding.fillStep1({
            displayName: 'E2E Coach',
            tagline: 'Coach specializzato in HIIT',
            bio: 'Bio professionale del coach per test automatizzato.',
        });
        await onboarding.continueToNextStep();
        await onboarding.selectSpecializations(1);
        await onboarding.selectMode('online');
        await onboarding.continueToNextStep();
        await onboarding.continueToNextStep(); // step 3 — certifications, optional
    }

    test('unauthenticated user is redirected to login', async ({ page }) => {
        await page.context().clearCookies();
        await page.goto('/coach/onboarding');
        await expect(page).toHaveURL(/auth\/login/);
    });

    test('coach without completed onboarding is redirected to onboarding from dashboard', async ({
        page,
    }) => {
        await page.goto('/coach/dashboard');
        await expect(page).toHaveURL(/coach\/onboarding/);
    });

    test('coach can log out from the onboarding header', async ({ page }) => {
        await onboarding.goto();
        await expect(page.getByTestId('logout-btn')).toBeVisible();

        await page.getByTestId('logout-btn').click();

        // Logout lands on the public home page and the session is gone.
        await expect(page).toHaveURL(/\/$/);
        await expect(page.getByTestId('logout-btn')).toHaveCount(0);

        await page.goto('/coach/dashboard');
        await expect(page).toHaveURL(/auth\/login/);
        await expect(page.getByTestId('login-form')).toBeVisible();
    });

    test('stepper is read-only and clicking ahead does not change the step', async ({
        page,
    }) => {
        await onboarding.goto();
        await expect(page).toHaveURL(/coach\/onboarding(\?step=1)?$/);

        await page.getByTestId('step-nav-3').click();
        await page.getByTestId('step-nav-6').click();

        await expect(page).toHaveURL(/coach\/onboarding(\?step=1)?$/);
        await expect(onboarding.displayNameInput).toBeVisible();
    });

    test('step 1 renders correctly', async () => {
        await onboarding.goto();
        await onboarding.expectStep(1);
        await expect(onboarding.displayNameInput).toBeVisible();
        await expect(onboarding.taglineInput).toBeVisible();
        await expect(onboarding.bioInput).toBeVisible();
        await expect(onboarding.continueBtn).toBeVisible();
    });

    test('step 1 saves display name and navigates to step 2', async () => {
        await onboarding.goto();
        await onboarding.fillStep1({
            displayName: 'E2E Test Coach',
            tagline: 'Coach specializzato in HIIT',
            bio: 'Bio professionale del coach per test automatizzato.',
        });
        await onboarding.continueToNextStep();

        await expect(onboarding.specializationSearch).toBeVisible();
    });

    test('step 1 with a short bio surfaces the error and does not advance', async ({
        page,
    }) => {
        await onboarding.goto();
        await onboarding.fillStep1({
            displayName: 'E2E Test Coach',
            tagline: 'Coach specializzato in HIIT',
            bio: 'corta',
        });
        await onboarding.continueToNextStep({ expectAdvance: false });

        await expect(page.getByTestId('error-bio')).toBeInViewport();
        await expect(page).toHaveURL(/coach\/onboarding/);
        await expect(onboarding.specializationSearch).toBeHidden();
    });

    test('step 2 shows specializations and allows selection', async () => {
        await onboarding.goto();
        await onboarding.fillStep1({
            displayName: 'E2E Coach',
            tagline: 'Coach specializzato in HIIT',
            bio: 'Bio professionale del coach per test automatizzato.',
        });
        await onboarding.continueToNextStep();

        const specBtns = onboarding.page.locator('[data-testid^="spec-"]');
        await expect(specBtns.first()).toBeVisible({ timeout: 15000 });
        const count = await specBtns.count();
        expect(count).toBeGreaterThanOrEqual(1);

        await onboarding.selectSpecializations(2);
        await expect(onboarding.page.getByTestId('selected-count')).toHaveText(
            '2 / 10 selezionate',
        );
    });

    test('step 2 saves and navigates to step 3 (certifications)', async () => {
        await onboarding.goto();
        await onboarding.fillStep1({
            displayName: 'E2E Coach',
            tagline: 'Coach specializzato in HIIT',
            bio: 'Bio professionale del coach per test automatizzato.',
        });
        await onboarding.continueToNextStep();

        await onboarding.selectSpecializations(1);
        await onboarding.selectMode('online');
        await onboarding.continueToNextStep();

        await expect(onboarding.page.getByTestId('add-cert-btn')).toBeVisible();
    });

    test('step 4 allows adding availability slots', async () => {
        await reachAvailability();

        await onboarding.openDaySlot(0); // Monday
        await onboarding.openDaySlot(2); // Wednesday
        await expect(
            onboarding.page.getByTestId('slot-start-0-0'),
        ).toBeVisible();
        await expect(
            onboarding.page.getByTestId('slot-start-2-0'),
        ).toBeVisible();
    });

    test('step 6 shows pricing fields and savings calculation', async () => {
        await reachAvailability();
        await onboarding.openDaySlot(0);
        await onboarding.setSessionDuration('60');
        await onboarding.setCancellationWindow('24');
        await onboarding.continueToNextStep();
        await onboarding.continueToNextStep(); // step 5 — sede, optional

        await expect(
            onboarding.page.getByTestId('input-single-price'),
        ).toBeVisible();

        await onboarding.setSinglePrice(50);
        await onboarding.addPackage({
            name: 'Pack 5',
            sessions: 5,
            price: 220,
        });

        await expect(onboarding.page.locator('text=Risparmi')).toBeVisible();
    });

    test('step 7 stores an uploaded gallery photo and marks the step complete', async ({
        page,
    }) => {
        await onboarding.goto();
        await onboarding.fillStep1({
            displayName: 'E2E Coach',
            tagline: 'Coach specializzato in HIIT',
            bio: 'Bio professionale del coach per test automatizzato.',
        });
        await onboarding.continueToNextStep();
        await onboarding.selectSpecializations(1);
        await onboarding.selectMode('online');
        await onboarding.continueToNextStep();
        await onboarding.continueToNextStep(); // step 3 — certifications, optional
        await onboarding.openDaySlot(0); // step 4 — availability, required
        await onboarding.setSessionDuration('60');
        await onboarding.setCancellationWindow('24');
        await onboarding.continueToNextStep();
        await onboarding.continueToNextStep(); // step 5 — sede, optional
        await onboarding.setSinglePrice(50); // step 6 — pricing, required
        await onboarding.continueToNextStep();

        await expect(
            page.getByTestId('upload-photo-slot').first(),
        ).toBeVisible();
        await onboarding.expectStepDone(7, false);

        // The upload runs after the step PUT lands, so a hard navigation here
        // would abort the in-flight request. Track the upload explicitly, then
        // go back to step 7 using the read-only flow's "Indietro" button.
        const uploaded = page.waitForResponse(
            (r) =>
                r.url().includes('upload-gallery-photo') && r.status() === 200,
        );
        await onboarding.uploadGalleryPhoto();
        await onboarding.continueToNextStep();
        await uploaded;
        await page.waitForLoadState('networkidle');

        // Step 8 — Policy. The stepper only marks a *past* step as done, so
        // assert the completed check while the gallery is not the current step.
        await onboarding.expectStepDone(7);

        // Back on step 7 the photo must still be there — it is stored server-side,
        // not just held in the browser.
        await onboarding.backBtn.click();
        await expect(page.getByTestId('gallery-photo-0')).toBeVisible();
        await onboarding.expectStepDone(7, false);
    });

    test('publishing without accepting the terms surfaces the error on screen', async ({
        page,
    }) => {
        await onboarding.goto();
        await onboarding.fillStep1({
            displayName: 'E2E Coach',
            tagline: 'Coach specializzato in HIIT',
            bio: 'Bio professionale del coach per test automatizzato.',
        });
        await onboarding.continueToNextStep();
        await onboarding.selectSpecializations(1);
        await onboarding.selectMode('online');
        await onboarding.continueToNextStep();
        await onboarding.continueToNextStep(); // step 3 — certifications, optional
        await onboarding.openDaySlot(0); // step 4 — availability, required
        await onboarding.setSessionDuration('60');
        await onboarding.setCancellationWindow('24');
        await onboarding.continueToNextStep();
        await onboarding.continueToNextStep(); // step 5 — sede, optional
        await onboarding.setSinglePrice(50); // step 6 — pricing, required
        await onboarding.continueToNextStep();
        await onboarding.continueToNextStep(); // step 7 — gallery, optional
        await onboarding.continueToNextStep(); // step 8 — policy, optional

        await expect(page.getByTestId('review-profile')).toBeVisible();
        await onboarding.continueToNextStep({ expectAdvance: false });

        await expect(page).toHaveURL(/coach\/onboarding/);
        await expect(page.getByTestId('error-terms')).toBeInViewport();
    });

    test('contact preferences persist when going back from review', async ({
        page,
    }) => {
        await onboarding.goto();
        await onboarding.fillStep1({
            displayName: 'E2E Coach',
            tagline: 'Coach specializzato in HIIT',
            bio: 'Bio professionale del coach per test automatizzato.',
        });
        await onboarding.continueToNextStep();
        await onboarding.selectSpecializations(1);
        await onboarding.selectMode('online');
        await onboarding.continueToNextStep();
        await onboarding.continueToNextStep(); // step 3 — certifications, optional
        await onboarding.openDaySlot(0); // step 4 — availability, required
        await onboarding.setSessionDuration('60');
        await onboarding.setCancellationWindow('24');
        await onboarding.continueToNextStep();
        await onboarding.continueToNextStep(); // step 5 — sede, optional
        await onboarding.setSinglePrice(50); // step 6 — pricing, required
        await onboarding.continueToNextStep();
        await onboarding.continueToNextStep(); // step 7 — gallery, optional
        // Now on step 8 — policy, optional.
        await expect(onboarding.contactFreeConsultationToggle).toHaveAttribute(
            'aria-pressed',
            'false',
        );
        await expect(onboarding.contactDirectBookingToggle).toHaveAttribute(
            'aria-pressed',
            'true',
        );

        // Enable "Contatto diretto", disable "Prenotazione diretta".
        await onboarding.setContactOption('direct_message', true);
        await onboarding.setContactOption('direct_booking', false);

        // Continue to step 9 and come back.
        await onboarding.continueToNextStep();
        await expect(page.getByTestId('review-profile')).toBeVisible();
        await onboarding.backBtn.click();

        await expect(
            page.getByTestId('toggle-contact-direct_message'),
        ).toHaveAttribute('aria-pressed', 'true');
        await expect(onboarding.contactDirectBookingToggle).toHaveAttribute(
            'aria-pressed',
            'false',
        );
    });

    test('full onboarding flow publishes profile and redirects to dashboard', async ({
        page,
    }) => {
        await onboarding.goto();

        // Step 1 — profilo
        await onboarding.fillStep1({
            displayName: 'E2E Coach Published',
            tagline: 'Coach HIIT',
            bio: 'Bio professionale.',
        });
        await onboarding.continueToNextStep();

        // Step 2 — specialità
        await onboarding.selectSpecializations(1);
        await onboarding.selectMode('online');
        await onboarding.continueToNextStep();

        // Step 3 — certificazioni (optional)
        await onboarding.continueToNextStep();

        // Step 4 — disponibilità
        await onboarding.openDaySlot(0);
        await onboarding.setSessionDuration('60');
        await onboarding.setCancellationWindow('24');
        await onboarding.continueToNextStep();

        // Step 5 — sede (optional)
        await onboarding.continueToNextStep();

        // Step 6 — tariffe
        await onboarding.setSinglePrice(50);
        await onboarding.continueToNextStep();

        // Step 7 — gallery (optional)
        await onboarding.continueToNextStep();

        // Step 8 — policy (optional)
        await onboarding.continueToNextStep();

        // Step 9 — revisione
        await expect(page.getByTestId('review-profile')).toBeVisible();
        await expect(page.getByTestId('checklist-profile')).toBeVisible();
        await onboarding.acceptTerms();
        await onboarding.continueToNextStep();

        await expect(page).toHaveURL(/coach\/dashboard/);
    });

    test('dashboard is accessible after onboarding completion', async ({
        page,
        laravel,
    }) => {
        // Mark onboarding as complete
        await laravel.callFunction(
            'Modules\\Users\\Tests\\E2e\\Support\\CoachFixtures::markOnboardingComplete',
        );

        await page.goto('/coach/dashboard');
        await expect(page).not.toHaveURL(/coach\/onboarding/);
    });
});
