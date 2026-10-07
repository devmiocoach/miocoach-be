import { onBeforeUnmount, onMounted, ref } from 'vue';

export function useCurrentTime(intervalMs = 60_000) {
    const now = ref(new Date());
    let timer: ReturnType<typeof setInterval> | null = null;

    onMounted(() => {
        timer = setInterval(() => {
            now.value = new Date();
        }, intervalMs);
    });

    onBeforeUnmount(() => {
        if (timer) clearInterval(timer);
    });

    return { now };
}
