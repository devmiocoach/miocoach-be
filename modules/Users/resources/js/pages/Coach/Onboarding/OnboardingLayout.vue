<script setup lang="ts">
import AppLogo from '@/components/AppLogo.vue';
import { Button } from '@/components/ui/button';
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    Check,
    LogOut,
    Save,
    SkipForward,
    Sparkles,
} from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    currentStep: number;
    totalSteps: number;
    completedSteps: number[];
    title: string;
    saving?: boolean;
    canContinue?: boolean;
}>();

defineEmits<{
    back: [];
    continue: [];
    saveDraft: [];
}>();

const isStepDone = (step: number) => props.completedSteps.includes(step);

const STEPS = [
    { label: 'Profilo', weight: 18 },
    { label: 'Specialità', weight: 12 },
    { label: 'Certificazioni', weight: 14 },
    { label: 'Disponibilità', weight: 10 },
    { label: 'Sede', weight: 10 },
    { label: 'Tariffe', weight: 12 },
    { label: 'Gallery', weight: 12 },
    { label: 'Policy', weight: 6 },
    { label: 'Revisione', weight: 6 },
];

// Sede is optional for the publish gate (see `resolveIncompleteSections`), so
// it must be skippable here too.
const OPTIONAL_STEPS = [3, 5, 7, 8];

const progress = computed(() =>
    Math.round((props.currentStep / props.totalSteps) * 100),
);

// Step 9 is the review itself and never lands in `completedSteps`.
const COMPLETABLE_STEPS = STEPS.slice(0, -1);
const totalWeight = COMPLETABLE_STEPS.reduce((a, s) => a + s.weight, 0);

// Driven by what the server marked complete, not by where the coach happens to
// be standing — clicking ahead in the stepper must not inflate the percentage.
const completenessPercent = computed(() => {
    const doneWeight = COMPLETABLE_STEPS.reduce(
        (a, s, i) => (isStepDone(i + 1) ? a + s.weight : a),
        0,
    );
    return Math.round((doneWeight / totalWeight) * 100);
});

const nextStepLabel = computed(() => STEPS[props.currentStep]?.label ?? null);
const nextStepWeight = computed(() => STEPS[props.currentStep]?.weight ?? 0);

const isOptional = computed(() => OPTIONAL_STEPS.includes(props.currentStep));

const handleLogout = () => {
    router.post(route('logout'));
};
</script>

<template>
    <Head :title="`Onboarding — ${title}`" />

    <div class="flex min-h-screen flex-col bg-white dark:bg-gray-950">
        <!-- Topbar -->
        <header
            class="sticky top-0 z-20 border-b border-gray-200/60 bg-white/85 backdrop-blur-xl dark:border-gray-800/60 dark:bg-gray-950/85"
        >
            <div
                class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-6"
            >
                <Link
                    href="/"
                    class="flex shrink-0 items-center transition-opacity hover:opacity-80"
                >
                    <AppLogo size="md" :showText="true" />
                </Link>

                <div class="flex items-center gap-4">
                    <!-- Stepper -->
                    <ol class="hidden items-center gap-1.5 lg:flex">
                        <template v-for="(step, i) in STEPS" :key="i">
                            <div
                                :data-testid="`step-nav-${i + 1}`"
                                :title="step.label"
                                :aria-current="
                                    i === currentStep - 1 ? 'step' : undefined
                                "
                                :class="[
                                    'flex h-7 min-w-7 items-center justify-center rounded-full border text-[11px] font-semibold',
                                    i === currentStep - 1 &&
                                        'border-teal-600 bg-teal-600 text-white shadow-[0_0_0_4px_rgba(13,148,136,0.18)]',
                                    i !== currentStep - 1 &&
                                        isStepDone(i + 1) &&
                                        'border-transparent bg-teal-600 text-white',
                                    i !== currentStep - 1 &&
                                        !isStepDone(i + 1) &&
                                        'border-gray-200 bg-white text-gray-400 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-500',
                                ]"
                            >
                                <Check
                                    v-if="
                                        isStepDone(i + 1) &&
                                        i !== currentStep - 1
                                    "
                                    class="h-3.5 w-3.5"
                                />
                                <span v-else>{{ i + 1 }}</span>
                            </div>
                            <div
                                v-if="i < STEPS.length - 1"
                                :class="[
                                    'h-px w-4 transition-colors',
                                    isStepDone(i + 1)
                                        ? 'bg-teal-500'
                                        : 'bg-gray-200 dark:bg-gray-700',
                                ]"
                            />
                        </template>
                    </ol>

                    <!-- Step counter mobile -->
                    <span
                        class="text-sm font-medium text-gray-500 lg:hidden dark:text-gray-400"
                    >
                        Step {{ currentStep }} di {{ totalSteps }}
                    </span>

                    <Button
                        variant="ghost"
                        size="sm"
                        class="gap-2 text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
                        data-testid="logout-btn"
                        :title="
                            $t(
                                'I dati salvati restano disponibili al prossimo accesso',
                            )
                        "
                        @click="handleLogout"
                    >
                        <LogOut class="h-4 w-4" />
                        Esci
                    </Button>
                </div>
            </div>

            <!-- Progress bar -->
            <div
                class="h-[3px] w-full overflow-hidden bg-gray-100 dark:bg-gray-800"
            >
                <div
                    class="h-full rounded-r-full transition-all duration-500 ease-out"
                    :style="{
                        width: `${progress}%`,
                        backgroundImage:
                            'linear-gradient(90deg, #0d9488, #ED7A22)',
                    }"
                />
            </div>
        </header>

        <!-- Body: aside + content -->
        <main
            class="mx-auto flex w-full max-w-7xl flex-1 gap-10 px-6 py-12 lg:py-14"
        >
            <!-- Sticky aside (hidden on mobile) -->
            <aside class="hidden w-[340px] shrink-0 lg:block">
                <div class="sticky top-28 space-y-6">
                    <!-- Title area — populated by slot -->
                    <slot name="aside" />

                    <!-- Completeness bar -->
                    <div
                        class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900"
                    >
                        <div class="mb-2 flex items-baseline justify-between">
                            <span
                                class="text-xs font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400"
                            >
                                Completezza profilo
                            </span>
                            <span
                                class="text-2xl font-bold text-gray-900 dark:text-white"
                                >{{ completenessPercent }}%</span
                            >
                        </div>
                        <div
                            class="h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800"
                        >
                            <div
                                class="h-full rounded-full transition-all duration-500"
                                :style="{
                                    width: `${completenessPercent}%`,
                                    backgroundImage:
                                        'linear-gradient(90deg, #0d9488, #ED7A22)',
                                }"
                            />
                        </div>
                        <div
                            v-if="nextStepLabel"
                            class="mt-3 flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400"
                        >
                            <Sparkles
                                class="h-3.5 w-3.5"
                                style="color: #ed7a22"
                            />
                            Prossimo:
                            <span
                                class="font-semibold text-gray-900 dark:text-white"
                                >{{ nextStepLabel }}</span
                            >
                            →
                            <span style="color: #ed7a22"
                                >+{{ nextStepWeight }}%</span
                            >
                        </div>
                    </div>
                </div>
            </aside>

            <!-- Content area -->
            <section class="min-w-0 flex-1">
                <!-- Mobile title (aside slot content repeated) -->
                <div class="mb-6 lg:hidden">
                    <slot name="aside-mobile" />
                </div>
                <slot />
            </section>
        </main>

        <!-- Footer actions -->
        <footer
            class="sticky bottom-0 z-20 border-t border-gray-200/60 bg-white/85 backdrop-blur-xl dark:border-gray-800/60 dark:bg-gray-950/85"
        >
            <div
                class="mx-auto flex h-20 max-w-7xl items-center justify-between gap-3 px-6"
            >
                <Button
                    v-if="currentStep > 1"
                    variant="ghost"
                    class="gap-2 text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
                    data-testid="btn-back"
                    @click="$emit('back')"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Indietro
                </Button>
                <div v-else />

                <div class="flex items-center gap-2">
                    <Button
                        v-if="isOptional && currentStep < totalSteps"
                        variant="ghost"
                        class="gap-1.5 text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
                        data-testid="btn-skip"
                        @click="$emit('continue')"
                    >
                        <SkipForward class="h-4 w-4" />
                        Salta per ora
                    </Button>
                    <Button
                        variant="outline"
                        class="gap-2"
                        :disabled="saving || currentStep === totalSteps"
                        data-testid="btn-save-draft"
                        @click="$emit('saveDraft')"
                    >
                        <Save class="h-4 w-4" />
                        Salva bozza
                    </Button>
                    <Button
                        :disabled="saving || canContinue === false"
                        class="gap-2 bg-gradient-to-r from-teal-600 to-teal-400 px-6 text-white hover:from-teal-500 hover:to-teal-300"
                        data-testid="btn-continue"
                        @click="$emit('continue')"
                    >
                        <span v-if="saving" class="flex items-center gap-2">
                            <span
                                class="h-4 w-4 animate-spin rounded-full border-2 border-white border-t-transparent"
                            />
                            Salvataggio…
                        </span>
                        <template v-else>
                            {{
                                currentStep === totalSteps
                                    ? 'Pubblica il profilo'
                                    : 'Continua'
                            }}
                            <ArrowRight class="h-4 w-4" />
                        </template>
                    </Button>
                </div>
            </div>
        </footer>
    </div>
</template>
