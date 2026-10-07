import { expect, test as setup } from '@miocoach/laravel-playwright';

/**
 * Guard rail: this file runs `migrate:fresh`, which destroys every table it can
 * reach. Before touching anything, confirm the app answering on E2E_APP_URL is
 * actually the testing environment on its own database — a server started with
 * the dev .env would otherwise wipe real data.
 */
setup('setup the database', async ({ laravel }) => {
    const environment = await laravel.config('app.env');
    const connection = (await laravel.config('database.default')) as string;
    const database = await laravel.config(
        `database.connections.${connection}.database`,
    );

    expect(
        environment,
        'E2E target is not the testing environment — refusing to run migrate:fresh',
    ).toBe('testing');

    expect(
        String(database),
        'E2E database name must end in _test — refusing to run migrate:fresh',
    ).toMatch(/_test$/);

    // migrate:fresh already runs the module migrations (they are registered
    // globally). Rolling them back afterwards with module:migrate-refresh is
    // not dependency-ordered and dies on Postgres foreign keys, so only the
    // module seeders run on top.
    await laravel.artisan('migrate:fresh --seed');
    await laravel.artisan('module:seed --all');
});
