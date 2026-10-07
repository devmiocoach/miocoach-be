import { onMounted, onUnmounted, ref, watch, type Ref } from 'vue';

export function useInfiniteScroll(
    fetchMore: () => Promise<void>,
    hasMore: Ref<boolean>,
) {
    const sentinelRef = ref<HTMLElement | null>(null);
    const isLoading = ref(false);
    let observer: IntersectionObserver | null = null;
    let stopWatcher: (() => void) | null = null;

    const handleIntersect = async (entries: IntersectionObserverEntry[]) => {
        const entry = entries[0];
        if (entry?.isIntersecting && hasMore.value && !isLoading.value) {
            isLoading.value = true;
            try {
                await fetchMore();
            } finally {
                isLoading.value = false;
            }
        }
    };

    onMounted(() => {
        observer = new IntersectionObserver(handleIntersect, {
            rootMargin: '200px',
        });
        stopWatcher = watch(sentinelRef, (el) => {
            if (observer && el) observer.observe(el);
        });
    });

    onUnmounted(() => {
        stopWatcher?.();
        observer?.disconnect();
    });

    return { sentinelRef, isLoading };
}
