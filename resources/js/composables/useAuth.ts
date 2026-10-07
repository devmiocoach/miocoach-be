import type { Auth, User } from '@/types';
import { Role, type RoleValue } from '@/types/enums';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function useAuth() {
    const page = usePage();

    const auth = computed(() => page.props.auth as Auth);
    const user = computed<User | null>(() => auth.value?.user ?? null);
    const isAuthenticated = computed(() => user.value !== null);
    const roles = computed(() => user.value?.roles ?? []);

    const isCoach = computed(() => auth.value?.is_coach ?? false);
    const isClient = computed(() => auth.value?.is_client ?? false);
    const isSubscriber = computed(() => auth.value?.is_subscriber ?? false);

    function hasRole(role: RoleValue): boolean {
        return roles.value.includes(role);
    }

    function hasAnyRole(...checkRoles: RoleValue[]): boolean {
        return checkRoles.some((r) => roles.value.includes(r));
    }

    return {
        auth,
        user,
        isAuthenticated,
        roles,
        isCoach,
        isClient,
        isSubscriber,
        hasRole,
        hasAnyRole,
        Role,
    };
}
