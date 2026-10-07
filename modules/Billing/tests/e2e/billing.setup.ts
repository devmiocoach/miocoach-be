import { test as setup } from '@miocoach/laravel-playwright';

setup('seed billing subscriber fixtures', async ({ laravel }) => {
    await laravel.callFunction(
        'Modules\\Billing\\Tests\\Support\\BillingTestHelper::createSubscriberFixtures',
    );
});
