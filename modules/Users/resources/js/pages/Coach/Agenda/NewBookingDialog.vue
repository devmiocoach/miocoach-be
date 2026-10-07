<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import Input from '@/components/ui/input/Input.vue';
import Label from '@/components/ui/label/Label.vue';
import Textarea from '@/components/ui/textarea/Textarea.vue';
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import type {
    AgendaClient,
    ServiceType,
} from '@modules/Users/resources/js/types/agenda';

import RecurringBookingFields from './RecurringBookingFields.vue';

const props = defineProps<{
    clients: AgendaClient[];
    serviceTypes: ServiceType[];
    defaultDuration: number;
    defaults: { date?: string; time?: string };
}>();

const open = defineModel<boolean>('open', { required: true });

const clientSearch = ref('');

const filteredClients = computed(() => {
    if (!clientSearch.value) return props.clients;
    const q = clientSearch.value.toLowerCase();
    return props.clients.filter((c) => c.name?.toLowerCase().includes(q));
});

const form = useForm({
    client_id: null as number | null,
    service_type: '',
    starts_at: '',
    ends_at: '',
    notes: '',
    price: null as number | null,
    recurrence_rule: null as string | null,
});

watch(open, (val) => {
    if (val) {
        form.reset();
        clientSearch.value = '';
        if (props.defaults.date) {
            const time = props.defaults.time || '09:00';
            form.starts_at = `${props.defaults.date}T${time}`;
            const start = new Date(`${props.defaults.date}T${time}`);
            const end = new Date(
                start.getTime() + props.defaultDuration * 60 * 1000,
            );
            form.ends_at = end.toISOString().slice(0, 16);
        }
    }
});

function selectClient(id: number, name: string | null) {
    form.client_id = id;
    clientSearch.value = name || '';
}

function updateEndTime() {
    if (!form.starts_at) return;
    const start = new Date(form.starts_at);
    const end = new Date(start.getTime() + props.defaultDuration * 60 * 1000);
    form.ends_at = end.toISOString().slice(0, 16);
}

function submit() {
    form.post(route('coach.agenda.bookings.store'), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="max-w-md" data-testid="new-booking-dialog">
            <DialogHeader>
                <DialogTitle>{{ $t('New booking') }}</DialogTitle>
                <DialogDescription>
                    {{ $t('Schedule a new session with a client') }}
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <!-- Client search -->
                <div class="space-y-1.5">
                    <Label>{{ $t('Client') }}</Label>
                    <div class="relative">
                        <Input
                            v-model="clientSearch"
                            :placeholder="$t('Search client...')"
                            data-testid="booking-client-search"
                            @focus="form.client_id = null"
                        />
                        <div
                            v-if="
                                !form.client_id &&
                                clientSearch &&
                                filteredClients.length > 0
                            "
                            class="absolute z-10 mt-1 max-h-40 w-full overflow-y-auto rounded-md border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800"
                        >
                            <button
                                v-for="client in filteredClients.slice(0, 8)"
                                :key="client.id"
                                type="button"
                                class="block w-full px-3 py-2 text-left text-sm hover:bg-gray-100 dark:hover:bg-gray-700"
                                @click="selectClient(client.id, client.name)"
                            >
                                {{ client.name }}
                            </button>
                        </div>
                    </div>
                    <p
                        v-if="form.errors.client_id"
                        class="text-xs text-red-500"
                    >
                        {{ form.errors.client_id }}
                    </p>
                </div>

                <!-- Service type -->
                <div class="space-y-1.5">
                    <Label>{{ $t('Service type') }}</Label>
                    <select
                        v-model="form.service_type"
                        class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                        data-testid="booking-service-type"
                    >
                        <option value="" disabled>
                            {{ $t('Select...') }}
                        </option>
                        <option
                            v-for="st in serviceTypes"
                            :key="st.value"
                            :value="st.value"
                        >
                            {{ st.label }}
                        </option>
                    </select>
                    <p
                        v-if="form.errors.service_type"
                        class="text-xs text-red-500"
                    >
                        {{ form.errors.service_type }}
                    </p>
                </div>

                <!-- Date/Time -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1.5">
                        <Label>{{ $t('Start') }}</Label>
                        <Input
                            v-model="form.starts_at"
                            type="datetime-local"
                            data-testid="booking-starts-at"
                            @change="updateEndTime"
                        />
                        <p
                            v-if="form.errors.starts_at"
                            class="text-xs text-red-500"
                        >
                            {{ form.errors.starts_at }}
                        </p>
                    </div>
                    <div class="space-y-1.5">
                        <Label>{{ $t('End') }}</Label>
                        <Input
                            v-model="form.ends_at"
                            type="datetime-local"
                            data-testid="booking-ends-at"
                        />
                        <p
                            v-if="form.errors.ends_at"
                            class="text-xs text-red-500"
                        >
                            {{ form.errors.ends_at }}
                        </p>
                    </div>
                </div>

                <!-- Recurrence -->
                <RecurringBookingFields v-model="form.recurrence_rule" />

                <!-- Notes -->
                <div class="space-y-1.5">
                    <Label>{{ $t('Notes') }}</Label>
                    <Textarea
                        v-model="form.notes"
                        :placeholder="$t('Optional notes...')"
                        rows="2"
                    />
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="open = false"
                    >
                        {{ $t('Cancel') }}
                    </Button>
                    <Button
                        type="submit"
                        :disabled="
                            form.processing ||
                            !form.client_id ||
                            !form.service_type ||
                            !form.starts_at ||
                            !form.ends_at
                        "
                        data-testid="booking-submit"
                    >
                        {{ $t('Create') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
