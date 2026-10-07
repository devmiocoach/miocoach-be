import { test as base, expect } from '@playwright/test';

export type LaravelOptions = {
    laravelBaseUrl: string;
    laravelSecret?: string;
};

export interface Laravel {
    artisan(command: string): Promise<string>;
    config(key: string, value?: unknown): Promise<unknown>;
    callFunction<T = unknown>(fn: string, args?: unknown[]): Promise<T>;
}

class LaravelClient implements Laravel {
    constructor(
        private readonly baseUrl: string,
        private readonly secret?: string,
    ) {}

    private async call<T>(action: string, payload: Record<string, unknown>): Promise<T> {
        const headers: Record<string, string> = {
            'Content-Type': 'application/json',
            Accept: 'application/json',
        };

        if (this.secret) {
            headers['X-Playwright-Secret'] = this.secret;
        }

        const response = await fetch(`${this.baseUrl}/${action}`, {
            method: 'POST',
            headers,
            body: JSON.stringify(payload),
        });

        if (!response.ok) {
            const text = await response.text();
            throw new Error(
                `Laravel Playwright [${action}] failed (${response.status}): ${text}`,
            );
        }

        return response.json() as Promise<T>;
    }

    async artisan(command: string): Promise<string> {
        return this.call<string>('artisan', { command });
    }

    async config(key: string, value?: unknown): Promise<unknown> {
        return this.call<unknown>('config', { key, value });
    }

    async callFunction<T = unknown>(fn: string, args: unknown[] = []): Promise<T> {
        return this.call<T>('call', { fn, args });
    }
}

export const test = base.extend<{ laravel: Laravel } & LaravelOptions>({
    laravelBaseUrl: ['http://localhost/playwright', { option: true }],
    laravelSecret: [undefined as unknown as string, { option: true }],

    laravel: async ({ laravelBaseUrl, laravelSecret }, use) => {
        await use(new LaravelClient(laravelBaseUrl, laravelSecret));
    },
});

export { expect };
