<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <section class="bg-background dark:bg-background py-12 sm:py-24">
        <div class="mx-auto max-w-7xl px-4 sm:px-6">
            <div
                class="relative overflow-hidden rounded-[2.5rem] bg-slate-900 shadow-2xl dark:bg-slate-900"
            >
                <div
                    class="grid grid-cols-1 items-center gap-8 p-8 sm:p-12 lg:grid-cols-2 lg:p-16"
                >
                    <!-- Left Content -->
                    <div class="relative z-10 flex flex-col space-y-6">
                        <h2
                            class="text-3xl leading-tight font-bold text-white md:text-4xl lg:text-5xl"
                        >
                            Diventa Coach MioCoach
                        </h2>
                        <p
                            class="max-w-lg text-lg leading-relaxed text-slate-300 md:text-xl"
                        >
                            Unisciti alla più grande rete di professionisti del
                            fitness in Italia. Gestisci appuntamenti, pagamenti
                            e visibilità in un'unica piattaforma intuitiva.
                        </p>

                        <div class="flex flex-col gap-4 pt-4 sm:flex-row">
                            <Button
                                as-child
                                class="bg-primary hover:bg-primary/90 text-primary-foreground h-14 rounded-xl px-8 text-base font-semibold"
                            >
                                <Link :href="route('register')">
                                    Inizia ora gratuitamente
                                </Link>
                            </Button>
                            <Button
                                as-child
                                variant="outline"
                                class="h-14 rounded-xl border-slate-700 bg-slate-800/50 px-8 text-base font-semibold text-white backdrop-blur-sm transition-colors hover:bg-slate-800"
                            >
                                <Link href="#"> Scopri i vantaggi </Link>
                            </Button>
                        </div>
                    </div>

                    <!-- Right Illustration (CSS Mockup of Dashboard) -->
                    <div
                        class="relative z-10 mt-8 flex h-full min-h-[300px] w-full items-center justify-center perspective-[1000px] lg:mt-0 lg:justify-end"
                    >
                        <div
                            class="w-full max-w-[450px] translate-x-4 rotate-y-[-5deg] rotate-z-[2deg] transform overflow-hidden rounded-2xl border border-slate-200 bg-slate-50 shadow-2xl transition-all duration-500 ease-out hover:translate-x-0 hover:rotate-y-0 hover:rotate-z-0"
                        >
                            <!-- Window Header -->
                            <div
                                class="flex h-10 items-center gap-2 border-b border-slate-200 bg-slate-100 px-4"
                            >
                                <div
                                    class="h-3 w-3 rounded-full bg-red-400"
                                ></div>
                                <div
                                    class="h-3 w-3 rounded-full bg-amber-400"
                                ></div>
                                <div
                                    class="h-3 w-3 rounded-full bg-green-400"
                                ></div>
                            </div>
                            <!-- App Content -->
                            <div class="flex flex-col gap-6 bg-white p-6">
                                <!-- Profile Row -->
                                <div class="flex items-center gap-4">
                                    <div
                                        class="flex h-14 w-14 items-center justify-center overflow-hidden rounded-full border-2 border-white bg-slate-200 shadow-sm"
                                    >
                                        <!-- Placeholder avatar SVG -->
                                        <svg
                                            class="mt-2 h-10 w-10 text-slate-400"
                                            fill="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                d="M24 20.993V24H0v-2.996A14.977 14.977 0 0112.004 15c4.904 0 9.26 2.354 11.996 5.993zM16.002 8.999a4 4 0 11-8 0 4 4 0 018 0z"
                                            />
                                        </svg>
                                    </div>
                                    <div class="flex flex-col gap-2">
                                        <div
                                            class="h-4 w-32 rounded-md bg-slate-200"
                                        ></div>
                                        <div
                                            class="h-3 w-24 rounded-md bg-slate-100"
                                        ></div>
                                    </div>
                                </div>
                                <!-- Skeleton rows -->
                                <div class="flex flex-col gap-3">
                                    <div
                                        class="h-3 w-full rounded-md bg-slate-100"
                                    ></div>
                                    <div
                                        class="h-3 w-5/6 rounded-md bg-slate-100"
                                    ></div>
                                    <div
                                        class="h-3 w-4/6 rounded-md bg-slate-100"
                                    ></div>
                                </div>
                                <!-- Action Buttons -->
                                <div class="mt-2 grid grid-cols-2 gap-4">
                                    <div
                                        class="h-10 rounded-lg border border-emerald-100 bg-emerald-50"
                                    ></div>
                                    <div
                                        class="h-10 rounded-lg border border-amber-100 bg-amber-50"
                                    ></div>
                                </div>
                            </div>
                        </div>
                        <!-- Background glow effect -->
                        <div
                            class="bg-primary/20 absolute inset-0 -z-10 scale-75 rounded-full blur-[100px]"
                        ></div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
