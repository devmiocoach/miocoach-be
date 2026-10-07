import { test as setup } from '@miocoach/laravel-playwright';

setup('create coach for onboarding tests', async ({ laravel }) => {
    await laravel.callFunction('Modules\\Users\\Tests\\E2e\\Support\\CoachFixtures::createIncompleteCoach');
});
