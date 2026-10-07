<script setup lang="ts">
import AlertMessage from '@/components/AlertMessage.vue';
import PageTransition from '@/components/PageTransition.vue';
import { Head } from '@inertiajs/vue3';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="flex min-h-screen flex-col bg-white dark:bg-gray-950">
        <Head :title="title" />

        <main class="flex flex-grow">
            <!-- Left Side - Image (Hidden on mobile) -->
            <div
                class="relative hidden w-1/2 overflow-hidden bg-slate-900 lg:flex"
            >
                <img
                    src="https://blog.infs.com/wp-content/uploads/2024/07/How-to-assess-a-client-1024x673.jpg"
                    alt="Training Hub"
                    class="absolute inset-0 h-full w-full object-cover opacity-60"
                />
                <div
                    class="absolute inset-0 bg-gradient-to-t from-slate-900/90 to-slate-900/20"
                ></div>
                <div
                    class="relative z-10 flex h-full flex-col justify-end p-16"
                >
                    <h1
                        class="mb-6 text-5xl leading-tight font-bold text-white"
                    >
                        Trasforma il tuo<br />potenziale in<br />
                        <span class="text-orange-300">Performance.</span>
                    </h1>
                    <p class="mb-12 max-w-md text-lg text-gray-300">
                        Accedi alla piattaforma più avanzata per il coaching
                        personalizzato. Gestisci i tuoi progressi o guida i tuoi
                        atleti verso l'eccellenza.
                    </p>

                    <!-- Floating badge -->
                    <div
                        class="flex w-max items-center gap-4 rounded-xl border border-white/10 bg-white/10 p-4 backdrop-blur-md"
                    >
                        <div
                            class="flex items-center justify-center rounded-full bg-orange-400 p-2"
                        >
                            <svg
                                class="h-6 w-6 text-white"
                                fill="currentColor"
                                viewBox="0 0 20 20"
                            >
                                <path
                                    d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"
                                ></path>
                            </svg>
                        </div>
                        <div>
                            <div class="font-bold text-white">+2.5k Coach</div>
                            <div class="text-sm text-gray-300">
                                Certificati e attivi oggi
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side - Form -->
            <div
                class="flex w-full flex-col justify-center bg-white px-6 py-12 lg:w-1/2 lg:px-24 dark:bg-gray-950"
            >
                <div class="mx-auto w-full max-w-md">
                    <PageTransition>
                        <AlertMessage
                            :message="$page.props.status || $page.props.error"
                            :variant="$page.props.status ? 'success' : 'error'"
                            class="mb-6"
                            data-testid="alert"
                        />
                        <slot />
                    </PageTransition>
                </div>
            </div>
        </main>
    </div>
</template>
