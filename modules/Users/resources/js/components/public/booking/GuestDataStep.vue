<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { onMounted } from 'vue';

const props = defineProps<{
    guestData: { name: string; email: string; phone: string };
    acceptedTerms: boolean;
}>();

const emit = defineEmits<{
    'update:guestData': [{ name: string; email: string; phone: string }];
    'update:acceptedTerms': [boolean];
}>();

const page = usePage();

onMounted(() => {
    const user = (page.props as Record<string, unknown>).auth as
        | { user?: { name: string; email: string } }
        | undefined;
    if (user?.user && !props.guestData.name) {
        emit('update:guestData', {
            name: user.user.name,
            email: user.user.email,
            phone: props.guestData.phone,
        });
    }
});

const updateField = (field: 'name' | 'email' | 'phone', value: string) => {
    emit('update:guestData', { ...props.guestData, [field]: value });
};
</script>

<template>
    <div>
        <h2 class="mb-2 text-xl font-bold text-gray-900 dark:text-white">
            I tuoi dati
        </h2>
        <p class="mb-6 text-sm text-gray-500 dark:text-gray-400">
            Inserisci i dati per la prenotazione
        </p>

        <div class="max-w-md space-y-4">
            <div>
                <label
                    class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >Nome e Cognome *</label
                >
                <input
                    type="text"
                    :value="guestData.name"
                    class="w-full rounded-xl bg-[#eff4ff] px-4 py-3 text-gray-900 placeholder:text-gray-400 focus:ring-2 focus:ring-teal-500 focus:outline-none dark:bg-gray-800 dark:text-white"
                    placeholder="Mario Rossi"
                    @input="
                        updateField(
                            'name',
                            ($event.target as HTMLInputElement).value,
                        )
                    "
                />
            </div>

            <div>
                <label
                    class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >Email *</label
                >
                <input
                    type="email"
                    :value="guestData.email"
                    class="w-full rounded-xl bg-[#eff4ff] px-4 py-3 text-gray-900 placeholder:text-gray-400 focus:ring-2 focus:ring-teal-500 focus:outline-none dark:bg-gray-800 dark:text-white"
                    placeholder="mario@email.com"
                    @input="
                        updateField(
                            'email',
                            ($event.target as HTMLInputElement).value,
                        )
                    "
                />
            </div>

            <div>
                <label
                    class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >Telefono</label
                >
                <input
                    type="tel"
                    :value="guestData.phone"
                    class="w-full rounded-xl bg-[#eff4ff] px-4 py-3 text-gray-900 placeholder:text-gray-400 focus:ring-2 focus:ring-teal-500 focus:outline-none dark:bg-gray-800 dark:text-white"
                    placeholder="+39 333 1234567"
                    @input="
                        updateField(
                            'phone',
                            ($event.target as HTMLInputElement).value,
                        )
                    "
                />
            </div>

            <label class="flex cursor-pointer items-start gap-3 pt-2">
                <input
                    type="checkbox"
                    :checked="acceptedTerms"
                    class="mt-0.5 h-4 w-4 rounded border-gray-300 text-teal-500 focus:ring-teal-500 dark:border-gray-600"
                    @change="emit('update:acceptedTerms', !acceptedTerms)"
                />
                <span class="text-sm text-gray-600 dark:text-gray-400">
                    Accetto i
                    <a
                        href="/terms"
                        class="text-teal-600 underline dark:text-teal-400"
                        target="_blank"
                        >Termini e Condizioni</a
                    >
                    e la
                    <a
                        href="/privacy"
                        class="text-teal-600 underline dark:text-teal-400"
                        target="_blank"
                        >Privacy Policy</a
                    >
                </span>
            </label>
        </div>
    </div>
</template>
