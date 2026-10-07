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
import Label from '@/components/ui/label/Label.vue';
import Textarea from '@/components/ui/textarea/Textarea.vue';
import type { Auth } from '@/types';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { Calendar, MessageCircle, Sparkles } from 'lucide-vue-next';
import { computed, ref } from 'vue';

import type { PublicCoachData } from '../../../types/public';

const props = defineProps<{
    coach: PublicCoachData;
}>();

const page = usePage<{ auth: Auth }>();

type ChannelKey = 'booking' | 'free_consultation' | 'message';

interface Channel {
    key: ChannelKey;
    label: string;
    icon: typeof Calendar;
}

const tooltip = 'Accedi come cliente per contattare il coach';

const isCoachUser = computed(() => page.props.auth.is_coach === true);
const isGuest = computed(() => !page.props.auth.user);

const channels = computed<Channel[]>(() => {
    const list: Channel[] = [];

    if (props.coach.contact_options.direct_booking) {
        list.push({
            key: 'booking',
            label: 'Prenota una sessione',
            icon: Calendar,
        });
    }

    if (props.coach.contact_options.free_consultation) {
        list.push({
            key: 'free_consultation',
            label: 'Richiedi consulenza gratuita',
            icon: Sparkles,
        });
    }

    if (props.coach.contact_options.direct_message) {
        list.push({
            key: 'message',
            label: 'Invia un messaggio',
            icon: MessageCircle,
        });
    }

    return list;
});

const primary = computed(() => channels.value[0] ?? null);
const secondary = computed(() => channels.value.slice(1));

const price = computed(() => {
    if (!props.coach.price_per_session) return null;

    return `${Math.round(Number(props.coach.price_per_session))}€`;
});

const loginHref = computed(() => route('coach.public.login', props.coach.slug));
const bookHref = computed(() => route('coach.public.book', props.coach.slug));

const dialogOpen = ref(false);
const dialogType = ref<'free_consultation' | 'message'>('free_consultation');

const form = useForm({
    type: '',
    message: '',
});

function openDialog(type: Exclude<ChannelKey, 'booking'>) {
    dialogType.value = type;
    form.reset();
    form.clearErrors();
    form.type = type;
    dialogOpen.value = true;
}

function submit() {
    form.post(route('coach.public.contact', props.coach.slug), {
        preserveScroll: true,
        onSuccess: () => {
            dialogOpen.value = false;
        },
    });
}

function channelHref(channel: Channel): string | null {
    if (isGuest.value) return loginHref.value;

    if (channel.key === 'booking' && !isCoachUser.value) return bookHref.value;

    return null;
}

function channelAction(channel: Channel) {
    if (isGuest.value || isCoachUser.value || channel.key === 'booking') return;

    openDialog(channel.key);
}
</script>

<template>
    <div v-if="channels.length > 0">
        <!-- Desktop card (sidebar) -->
        <div
            class="hidden overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm lg:block dark:border-gray-800 dark:bg-gray-900"
            data-testid="contact-widget"
        >
            <div
                class="space-y-1 border-b border-gray-100 px-6 py-5 dark:border-gray-800"
            >
                <p
                    v-if="price"
                    class="text-xl font-bold text-gray-900 dark:text-white"
                >
                    {{ price }}
                    <span
                        class="text-sm font-normal text-gray-500 dark:text-gray-400"
                        >/sessione</span
                    >
                </p>
                <p
                    v-else
                    class="text-xl font-bold text-gray-900 dark:text-white"
                >
                    Contatta {{ coach.display_name }}
                </p>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Scegli come contattare
                    {{ coach.display_name.split(' ')[0] }}
                </p>
            </div>

            <div class="space-y-2 p-4">
                <template v-if="primary">
                    <Link
                        v-if="channelHref(primary)"
                        :href="channelHref(primary)!"
                        :data-testid="`contact-cta-${primary.key}`"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-teal-600 to-teal-500 px-6 py-3 text-sm font-semibold text-white shadow-[0_4px_16px_rgba(20,184,166,0.3)]"
                    >
                        <component :is="primary.icon" :size="16" />
                        {{ primary.label }}
                    </Link>
                    <Button
                        v-else
                        type="button"
                        class="w-full"
                        :disabled="isCoachUser"
                        :title="isCoachUser ? tooltip : undefined"
                        :data-testid="`contact-cta-${primary.key}`"
                        @click="channelAction(primary)"
                    >
                        <component :is="primary.icon" :size="16" />
                        {{ primary.label }}
                    </Button>
                </template>

                <template v-for="channel in secondary" :key="channel.key">
                    <Link
                        v-if="channelHref(channel)"
                        :href="channelHref(channel)!"
                        :data-testid="`contact-cta-${channel.key}`"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
                    >
                        <component :is="channel.icon" :size="16" />
                        {{ channel.label }}
                    </Link>
                    <Button
                        v-else
                        type="button"
                        variant="outline"
                        class="w-full"
                        :disabled="isCoachUser"
                        :title="isCoachUser ? tooltip : undefined"
                        :data-testid="`contact-cta-${channel.key}`"
                        @click="channelAction(channel)"
                    >
                        <component :is="channel.icon" :size="16" />
                        {{ channel.label }}
                    </Button>
                </template>
            </div>
        </div>

        <!-- Mobile sticky bar -->
        <div
            class="fixed inset-x-0 bottom-0 z-40 border-t border-gray-200/50 bg-white/90 px-4 py-3 backdrop-blur-xl lg:hidden dark:border-gray-800/50 dark:bg-gray-900/90"
            data-testid="contact-widget-bar"
        >
            <div
                class="mx-auto flex max-w-lg items-center justify-between gap-3"
            >
                <div v-if="price">
                    <span
                        class="text-lg font-bold text-gray-900 dark:text-white"
                        >{{ price }}</span
                    >
                    <span class="text-sm text-gray-500 dark:text-gray-400"
                        >/sessione</span
                    >
                </div>
                <template v-if="primary">
                    <Link
                        v-if="channelHref(primary)"
                        :href="channelHref(primary)!"
                        :class="[
                            'inline-flex shrink-0 items-center gap-2 rounded-xl bg-gradient-to-r from-teal-600 to-teal-500 px-6 py-3 text-sm font-semibold text-white shadow-[0_4px_16px_rgba(20,184,166,0.3)]',
                            { 'ml-auto': !price },
                        ]"
                        :data-testid="`contact-bar-cta-${primary.key}`"
                    >
                        <component :is="primary.icon" :size="16" />
                        {{ primary.label }}
                    </Link>
                    <Button
                        v-else
                        type="button"
                        :class="[{ 'ml-auto': !price }]"
                        :disabled="isCoachUser"
                        :title="isCoachUser ? tooltip : undefined"
                        :data-testid="`contact-bar-cta-${primary.key}`"
                        @click="channelAction(primary)"
                    >
                        <component :is="primary.icon" :size="16" />
                        {{ primary.label }}
                    </Button>
                </template>
            </div>
        </div>

        <Dialog v-model:open="dialogOpen">
            <DialogContent class="max-w-md" data-testid="contact-dialog">
                <DialogHeader>
                    <DialogTitle>
                        {{
                            dialogType === 'message'
                                ? 'Invia un messaggio'
                                : 'Richiedi consulenza gratuita'
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        {{
                            dialogType === 'message'
                                ? `Scrivi un messaggio a ${coach.display_name}.`
                                : 'Richiedi una consulenza gratuita con questo coach.'
                        }}
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="submit">
                    <div class="space-y-1.5">
                        <Label>Messaggio</Label>
                        <Textarea
                            v-model="form.message"
                            :required="dialogType === 'message'"
                            :placeholder="
                                dialogType === 'message'
                                    ? 'Il tuo messaggio al coach...'
                                    : 'Descrivi brevemente il tuo obiettivo (opzionale)...'
                            "
                            rows="4"
                            data-testid="contact-message"
                        />
                        <p
                            v-if="form.errors.message"
                            class="text-xs text-red-500"
                            data-testid="contact-message-error"
                        >
                            {{ form.errors.message }}
                        </p>
                        <p v-if="form.errors.type" class="text-xs text-red-500">
                            {{ form.errors.type }}
                        </p>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="dialogOpen = false"
                        >
                            Annulla
                        </Button>
                        <Button
                            type="submit"
                            :disabled="form.processing"
                            data-testid="contact-submit"
                        >
                            Invia richiesta
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
