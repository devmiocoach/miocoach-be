<script setup lang="ts">
import { useRoleGuard } from '@/composables/useRoleGuard';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Breadcrumb } from '@/types';
import { Role } from '@/types/enums';

defineProps<{
    title?: string;
    breadcrumbs?: Breadcrumb[];
}>();

const { isAuthorized, isChecking } = useRoleGuard({
    requiredRoles: [Role.CLIENT],
});
</script>

<template>
    <AppLayout
        v-if="!isChecking && isAuthorized"
        :title="title"
        :breadcrumbs="breadcrumbs"
    >
        <template v-if="$slots.header" #header>
            <slot name="header" />
        </template>
        <slot />
    </AppLayout>
    <div v-else-if="isChecking" class="min-h-screen" />
</template>
