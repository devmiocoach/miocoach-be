import { expect, type Locator, type Page } from '@playwright/test';

export class OnboardingPage {
    readonly page: Page;

    // Layout
    readonly continueBtn: Locator;
    readonly backBtn: Locator;
    readonly saveDraftBtn: Locator;

    // Step 1
    readonly displayNameInput: Locator;
    readonly taglineInput: Locator;
    readonly bioInput: Locator;

    // Step 2
    readonly specializationSearch: Locator;

    // Step 8
    readonly contactFreeConsultationToggle: Locator;
    readonly contactDirectMessageToggle: Locator;
    readonly contactDirectBookingToggle: Locator;

    constructor(page: Page) {
        this.page = page;
        this.continueBtn = page.getByTestId('btn-continue');
        this.backBtn = page.getByTestId('btn-back');
        this.saveDraftBtn = page.getByTestId('btn-save-draft');
        this.displayNameInput = page.getByTestId('input-display-name');
        this.taglineInput = page.getByTestId('input-tagline');
        this.bioInput = page.getByTestId('input-bio');
        this.specializationSearch = page.getByTestId('specialization-search');
        this.contactFreeConsultationToggle = page.getByTestId(
            'toggle-contact-free_consultation',
        );
        this.contactDirectMessageToggle = page.getByTestId(
            'toggle-contact-direct_message',
        );
        this.contactDirectBookingToggle = page.getByTestId(
            'toggle-contact-direct_booking',
        );
    }

    async goto() {
        await this.page.goto('/coach/onboarding');
    }

    async expectStep(n: number) {
        await expect(this.page.getByTestId(`step-nav-${n}`)).toBeVisible();
        await expect(this.continueBtn).toBeVisible();
    }

    async fillStep1(opts: {
        displayName: string;
        tagline?: string;
        bio?: string;
    }) {
        await this.displayNameInput.fill(opts.displayName);
        if (opts.tagline) await this.taglineInput.fill(opts.tagline);
        if (opts.bio) await this.bioInput.fill(opts.bio);
    }

    async continueToNextStep(opts: { expectAdvance?: boolean } = {}) {
        const expectAdvance = opts.expectAdvance ?? true;
        const before = await this.page
            .locator('[aria-current="step"]')
            .getAttribute('data-testid');

        if (expectAdvance) {
            // Wait for the wizard to actually move to another step (or leave
            // the wizard altogether), instead of blindly trusting `networkidle`.
            await Promise.all([
                this.page.waitForFunction(
                    (prev) => {
                        const cur = document.querySelector(
                            '[aria-current="step"]',
                        );
                        return !cur || cur.getAttribute('data-testid') !== prev;
                    },
                    before,
                    { timeout: 20000 },
                ),
                this.continueBtn.click(),
            ]);
            await this.page.waitForTimeout(250);
        } else {
            // Validation path: the step must NOT advance, the caller asserts
            // the inline error itself.
            await this.continueBtn.click();
            await this.page.waitForLoadState('networkidle');
            await this.page.waitForTimeout(600);
        }
    }

    async selectSpecializations(count = 2) {
        const btns = this.page.locator('[data-testid^="spec-"]');
        await btns.first().waitFor({ timeout: 8000 });
        const available = await btns.count();
        for (let i = 0; i < Math.min(count, available); i++) {
            await btns.nth(i).click();
            await this.page.waitForTimeout(100);
        }
    }

    async selectMode(mode: 'online' | 'in_person' | 'hybrid') {
        await this.page.getByTestId(`mode-${mode}`).click();
    }

    /** Toggling a day on adds its first slot. */
    async openDaySlot(day: number) {
        await this.page.getByTestId(`toggle-day-${day}`).click();
        await this.page.waitForTimeout(200);
    }

    /** A completed step shows a check instead of its number in the stepper. */
    async expectStepDone(step: number, done = true) {
        const dot = this.page.getByTestId(`step-nav-${step}`);
        const assertion = done
            ? expect(dot).not.toHaveText(String(step))
            : expect(dot).toHaveText(String(step));
        await assertion;
    }

    async uploadGalleryPhoto(name = 'gallery.png') {
        // 1×1 transparent PNG — small but a real image, so server-side
        // `image` validation passes.
        const buffer = Buffer.from(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            'base64',
        );
        await this.page
            .getByTestId('upload-photo-slot')
            .first()
            .locator('input[type="file"]')
            .setInputFiles({ name, mimeType: 'image/png', buffer });
        await this.page.waitForTimeout(300);
    }

    async setSessionDuration(minutes: '45' | '60' | '90') {
        await this.page.getByTestId(`duration-${minutes}`).click();
    }

    async setCancellationWindow(hours: '12' | '24' | '48') {
        await this.page.getByTestId(`cancel-window-${hours}`).click();
    }

    async setSinglePrice(euros: number) {
        await this.page.getByTestId('input-single-price').fill(String(euros));
    }

    async addPackage(opts: { name: string; sessions: number; price: number }) {
        await this.page.getByTestId('add-package-btn').click();
        await this.page.waitForTimeout(300);
        await this.page.getByTestId('package-name-0').fill(opts.name);
        await this.page
            .getByTestId('package-sessions-0')
            .fill(String(opts.sessions));
        await this.page.getByTestId('package-price-0').fill(String(opts.price));
    }

    async acceptTerms() {
        await this.page.getByTestId('accept-terms-checkbox').click();
        await this.page.waitForTimeout(200);
    }

    async setContactOption(
        key: 'free_consultation' | 'direct_message' | 'direct_booking',
        enabled: boolean,
    ) {
        const toggle = this.page.getByTestId(`toggle-contact-${key}`);
        const ariaPressed = await toggle.getAttribute('aria-pressed');
        const isEnabled = ariaPressed === 'true';
        if (isEnabled !== enabled) {
            await toggle.click();
            await this.page.waitForTimeout(150);
        }
    }
}
