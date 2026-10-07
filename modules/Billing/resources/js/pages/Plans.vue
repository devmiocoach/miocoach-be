<script setup lang="ts">
import SiteLayout from '@/layouts/SiteLayout.vue';
import ProductSection from '../components/ProductSection.vue';
import type { Product } from '../types';

defineProps<{
    products: Product[];
}>();
</script>

<template>
    <SiteLayout :title="$t('Piani & Tariffe')">
        <ProductSection :products="products">
            <div class="mx-auto max-w-4xl text-center">
                <h2
                    class="mt-2 text-4xl font-semibold tracking-tight text-gray-900 sm:text-5xl dark:text-white"
                >
                    {{ $t('Piani & Tariffe') }}
                </h2>
                <p
                    class="mx-auto mt-6 max-w-2xl text-lg text-gray-600 dark:text-gray-400"
                >
                    {{
                        $t(
                            'Scegli il piano più adatto alle tue esigenze. Cancella in qualsiasi momento.',
                        )
                    }}
                </p>
            </div>
        </ProductSection>
    </SiteLayout>
</template>
