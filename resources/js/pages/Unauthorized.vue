<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useAuth } from '@/composables/useAuth';
import { Head, Link, router } from '@inertiajs/vue3';

const { isCoach, isClient, isAuthenticated } = useAuth();

function logout() {
    router.post(route('logout'));
}
</script>

<template>
    <Head :title="$t('Unauthorized')" />
    <div
        class="flex min-h-screen items-center justify-center bg-gray-50 dark:bg-gray-900"
        data-testid="unauthorized-page"
    >
        <div class="w-full max-w-md space-y-8 text-center">
            <div>
                <h1 class="text-6xl font-bold text-gray-900 dark:text-gray-100">
                    403
                </h1>
                <h2
                    class="mt-4 text-2xl font-semibold text-gray-700 dark:text-gray-300"
                >
                    {{ $t('Access Denied') }}
                </h2>
                <p class="mt-2 text-gray-600 dark:text-gray-400">
                    {{
                        $t(
                            "You don't have permission to access this page. Please contact your administrator if you believe this is an error.",
                        )
                    }}
                </p>
            </div>

            <div class="flex flex-col items-center gap-3">
                <Button v-if="isCoach" as-child data-testid="go-dashboard-btn">
                    <Link :href="route('coach.dashboard')">
                        {{ $t('Go to Coach Dashboard') }}
                    </Link>
                </Button>

                <Button
                    v-else-if="isClient"
                    as-child
                    data-testid="go-dashboard-btn"
                >
                    <Link :href="route('client.dashboard')">
                        {{ $t('Go to My Dashboard') }}
                    </Link>
                </Button>

                <Button as-child variant="outline" data-testid="go-home-btn">
                    <Link :href="route('index')">
                        {{ $t('Go to Home') }}
                    </Link>
                </Button>

                <button
                    v-if="isAuthenticated"
                    class="text-primary/70 mt-2 text-sm font-medium underline-offset-4 hover:underline"
                    data-testid="logout-btn"
                    @click="logout"
                >
                    {{ $t('Logout') }}
                </button>
            </div>
        </div>
    </div>
</template>
