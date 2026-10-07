import { useAuth } from '@/composables/useAuth';
import type { RoleValue } from '@/types/enums';
import { router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

interface RoleGuardOptions {
    requiredRoles: RoleValue[];
    redirectTo?: string;
}

export function useRoleGuard(options: RoleGuardOptions) {
    const { isAuthenticated, hasAnyRole } = useAuth();
    const isChecking = ref(true);

    const isAuthorized = computed(() => {
        if (!isAuthenticated.value) return false;
        return hasAnyRole(...options.requiredRoles);
    });

    onMounted(() => {
        isChecking.value = false;

        if (!isAuthorized.value) {
            router.visit(options.redirectTo ?? route('unauthorized'), {
                replace: true,
            });
        }
    });

    return {
        isAuthorized,
        isChecking,
    };
}
