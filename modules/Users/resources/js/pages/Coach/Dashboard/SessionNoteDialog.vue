<script setup lang="ts">
import Button from '@/components/ui/button/Button.vue';
import Dialog from '@/components/ui/dialog/Dialog.vue';
import DialogContent from '@/components/ui/dialog/DialogContent.vue';
import DialogFooter from '@/components/ui/dialog/DialogFooter.vue';
import DialogHeader from '@/components/ui/dialog/DialogHeader.vue';
import DialogTitle from '@/components/ui/dialog/DialogTitle.vue';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

const props = defineProps<{
    open: boolean;
    clientId: number | null;
    clientName: string;
}>();

const emit = defineEmits<{
    close: [];
}>();

const form = useForm({
    content: '',
});

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            form.reset();
        }
    },
);

function submit() {
    if (!props.clientId) return;

    form.post(route('coach.clients.notes.store', props.clientId), {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
}
</script>

<template>
    <Dialog :open="open" @update:open="$event || emit('close')">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>
                    {{ $t('Notes') }} — {{ clientName }}
                </DialogTitle>
            </DialogHeader>

            <form @submit.prevent="submit" data-testid="note-form">
                <textarea
                    v-model="form.content"
                    class="focus:border-primary focus:ring-primary min-h-[120px] w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-500 focus:ring-1 dark:border-gray-700 dark:bg-gray-900 dark:text-white dark:placeholder:text-gray-400"
                    :placeholder="$t('Write a note...')"
                    data-testid="note-textarea"
                ></textarea>

                <p
                    v-if="form.errors.content"
                    class="mt-1 text-sm text-red-600 dark:text-red-400"
                >
                    {{ form.errors.content }}
                </p>

                <DialogFooter class="mt-4">
                    <Button
                        type="button"
                        variant="outline"
                        @click="emit('close')"
                    >
                        {{ $t('Cancel') }}
                    </Button>
                    <Button
                        type="submit"
                        :disabled="form.processing || !form.content.trim()"
                        data-testid="note-submit"
                    >
                        {{ $t('Save') }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
