import { computed, type Ref } from 'vue';

export interface PasswordStrength {
    score: number;
    label: string;
    color: string;
    bgColor: string;
    checks: {
        minLength: boolean;
        hasLower: boolean;
        hasUpper: boolean;
        hasDigit: boolean;
        hasSpecial: boolean;
    };
}

export function usePasswordStrength(password: Ref<string>): {
    strength: Ref<PasswordStrength>;
} {
    const strength = computed<PasswordStrength>(() => {
        const val = password.value;

        const checks = {
            minLength: val.length >= 8,
            hasLower: /[a-z]/.test(val),
            hasUpper: /[A-Z]/.test(val),
            hasDigit: /\d/.test(val),
            hasSpecial: /[^a-zA-Z0-9]/.test(val),
        };

        const score = Object.values(checks).filter(Boolean).length;

        if (val.length === 0) {
            return { score: 0, label: '', color: '', bgColor: '', checks };
        }

        if (score <= 1) {
            return {
                score,
                label: 'Molto debole',
                color: 'text-red-600 dark:text-red-400',
                bgColor: 'bg-red-500',
                checks,
            };
        }
        if (score === 2) {
            return {
                score,
                label: 'Debole',
                color: 'text-orange-600 dark:text-orange-400',
                bgColor: 'bg-orange-500',
                checks,
            };
        }
        if (score === 3) {
            return {
                score,
                label: 'Media',
                color: 'text-yellow-600 dark:text-yellow-400',
                bgColor: 'bg-yellow-500',
                checks,
            };
        }
        if (score === 4) {
            return {
                score,
                label: 'Buona',
                color: 'text-lime-600 dark:text-lime-400',
                bgColor: 'bg-lime-500',
                checks,
            };
        }
        return {
            score,
            label: 'Forte',
            color: 'text-emerald-600 dark:text-emerald-400',
            bgColor: 'bg-emerald-500',
            checks,
        };
    });

    return { strength };
}
