<?php

namespace Miocoach\LaravelPlaywright;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        Route::prefix('playwright')
            ->middleware('api')
            ->group(function () {
                Route::post('/artisan', function (Request $request) {
                    $this->authenticate($request);

                    Artisan::call($request->input('command', ''));

                    return response()->json(Artisan::output());
                });

                Route::post('/config', function (Request $request) {
                    $this->authenticate($request);

                    $key = $request->input('key');
                    $value = $request->input('value');

                    if ($value !== null) {
                        config([$key => $value]);
                    }

                    return response()->json(config($key));
                });

                Route::post('/call', function (Request $request) {
                    $this->authenticate($request);

                    $fn = $request->input('fn');
                    $args = $request->input('args', []);

                    [$class, $method] = explode('::', $fn, 2);

                    if (array_is_list($args)) {
                        $args = $this->bindPositionalArguments($class, $method, $args);
                    }

                    return response()->json(app()->call([$class, $method], $args));
                });
            });
    }

    /**
     * app()->call() matches arguments by parameter name only. Map a positional
     * list from the client onto the method's parameter names so string/int
     * arguments resolve instead of being treated as container dependencies.
     *
     * @param  list<mixed>  $args
     * @return array<string|int, mixed>
     */
    private function bindPositionalArguments(string $class, string $method, array $args): array
    {
        $parameters = (new \ReflectionMethod($class, $method))->getParameters();

        $bound = [];

        foreach ($args as $index => $value) {
            $parameter = $parameters[$index] ?? null;
            $bound[$parameter?->getName() ?? $index] = $value;
        }

        return $bound;
    }

    private function authenticate(Request $request): void
    {
        $secret = env('PLAYWRIGHT_SECRET');

        if ($secret && $request->header('X-Playwright-Secret') !== $secret) {
            abort(403, 'Unauthorized');
        }
    }
}
