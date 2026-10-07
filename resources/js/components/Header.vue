<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowRight } from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import IconMenu from '~icons/heroicons/bars-3';
import IconX from '~icons/heroicons/x-mark';
import AppLogo from './AppLogo.vue';
import DarkModeToggle from './DarkModeToggle.vue';

const page = usePage();

const isSearchPage = computed(() => route().current('coach.search'));

// Navigation dynamically computed based on current route
const landingNav = computed(() => {
    void page.component;

    return [
        {
            title: 'Cerca Coach',
            url: route('index'),
            active: route().current('index'),
        },
        {
            title: 'Piani & Tariffe',
            url: route('billing.plans'),
            active: route().current('billing.plans'),
        },
        {
            title: 'Come Funziona',
            url: route('index') + '#come-funziona',
            active: false,
        },
        {
            title: 'FAQ',
            url: route('index') + '#faq',
            active: false,
        },
    ];
});

const isScrolled = ref(false);
const mobileMenuOpen = ref(false);
const headerRef = ref<HTMLElement | null>(null);

const handleScroll = () => {
    isScrolled.value = window.scrollY > 10;
};

onMounted(() => {
    window.addEventListener('scroll', handleScroll);
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', handleScroll);
});
</script>

<template>
    <header
        ref="headerRef"
        class="fixed top-0 right-0 left-0 z-50 transition-all duration-300"
        :class="[
            isScrolled
                ? 'dark:border-b-border/25 dark:bg-background/90 border-b bg-white/90 shadow-sm backdrop-blur-md'
                : isSearchPage
                    ? 'bg-white dark:bg-background border-b border-gray-100 dark:border-gray-800'
                    : 'bg-transparent',
        ]"
    >
        <nav class="mx-auto max-w-7xl px-6 py-4">
            <div class="flex items-center justify-between">
                <!-- Logo (Left) -->
                <Link
                    href="/"
                    class="flex shrink-0 items-center transition-opacity hover:opacity-80"
                >
                    <AppLogo size="md" :showText="true" />
                </Link>

                <!-- Landing navigation - Centered (hidden on search page) -->
                <div v-if="!isSearchPage" class="hidden items-center space-x-6 lg:flex">
                    <component
                        :is="item.url.includes('#') ? 'a' : Link"
                        v-for="item in landingNav"
                        :key="item.title"
                        :href="item.url"
                        class="hover:text-primary text-sm font-medium transition-colors"
                        :class="[
                            item.active
                                ? 'text-primary border-primary border-b-2 pb-1'
                                : 'text-muted-foreground',
                        ]"
                    >
                        {{ item.title }}
                    </component>
                </div>

                <!-- Right side actions -->
                <div class="hidden items-center space-x-4 lg:flex">
                    <DarkModeToggle />

                    <Link
                        v-if="!$page.props.auth?.user"
                        :href="route('login')"
                        class="text-muted-foreground hover:text-foreground text-sm font-medium transition-colors"
                    >
                        Login
                    </Link>

                    <Link
                        v-if="!$page.props.auth?.user"
                        :href="route('register', { type: 'coach' })"
                        class="bg-primary text-primary-foreground hover:bg-primary/90 inline-flex items-center justify-center rounded-md px-5 py-2.5 text-sm font-semibold transition-all duration-200"
                    >
                        Inizia come Coach
                    </Link>

                    <Link
                        v-if="
                            route().has('dashboard') && $page.props.auth?.user
                        "
                        :href="route('dashboard')"
                        class="bg-primary text-primary-foreground hover:bg-primary/90 inline-flex items-center justify-center rounded-md px-5 py-2.5 text-sm font-semibold transition-all duration-200"
                    >
                        Dashboard
                    </Link>
                </div>

                <!-- Mobile Menu Button -->
                <div class="flex items-center gap-1 lg:hidden">
                    <DarkModeToggle />
                    <button
                        @click="mobileMenuOpen = !mobileMenuOpen"
                        class="text-muted-foreground hover:bg-accent hover:text-accent-foreground rounded-xl p-2 transition-colors duration-200"
                    >
                        <IconMenu v-if="!mobileMenuOpen" class="h-6 w-6" />
                        <IconX v-else class="h-6 w-6" />
                    </button>
                </div>
            </div>

            <!-- Mobile Menu -->
            <Transition
                enter-active-class="transition-all duration-300 ease-out"
                enter-from-class="opacity-0 transform -translate-y-2"
                enter-to-class="opacity-100 transform translate-y-0"
                leave-active-class="transition-all duration-200 ease-in"
                leave-from-class="opacity-100 transform translate-y-0"
                leave-to-class="opacity-0 transform -translate-y-2"
            >
                <div
                    v-if="mobileMenuOpen"
                    class="border-border/40 bg-background/95 mt-4 rounded-lg border-t p-4 shadow-lg backdrop-blur-md lg:hidden"
                >
                    <div class="flex flex-col space-y-4">
                        <template v-if="!isSearchPage">
                            <component
                                :is="item.url.includes('#') ? 'a' : Link"
                                v-for="item in landingNav"
                                :key="item.title"
                                :href="item.url"
                                class="hover:text-primary text-base font-medium transition-colors"
                                :class="
                                    item.active
                                        ? 'text-primary'
                                        : 'text-foreground'
                                "
                                @click="mobileMenuOpen = false"
                            >
                                {{ item.title }}
                            </component>
                        </template>

                        <div
                            class="border-border/60 mt-4 flex flex-col gap-3 border-t pt-4"
                        >
                            <Link
                                v-if="!$page.props.auth?.user"
                                :href="route('login')"
                                class="border-border text-foreground hover:bg-accent rounded-md border px-4 py-2.5 text-center text-sm font-medium transition-all"
                                @click="mobileMenuOpen = false"
                            >
                                Login
                            </Link>
                            <Link
                                v-if="!$page.props.auth?.user"
                                :href="route('register', { type: 'coach' })"
                                class="bg-primary text-primary-foreground hover:bg-primary/90 flex items-center justify-center gap-2 rounded-md px-4 py-2.5 text-sm font-semibold transition-all"
                                @click="mobileMenuOpen = false"
                            >
                                Inizia come Coach
                                <ArrowRight class="h-4 w-4" />
                            </Link>
                        </div>
                    </div>
                </div>
            </Transition>
        </nav>
    </header>
</template>
