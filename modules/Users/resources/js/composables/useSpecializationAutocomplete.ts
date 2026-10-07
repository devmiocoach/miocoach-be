import { ref, watch } from 'vue';

interface SpecializationOption {
    id: number;
    name: string;
    slug: string;
}

export function useSpecializationAutocomplete() {
    const query = ref('');
    const suggestions = ref<SpecializationOption[]>([]);
    const isLoading = ref(false);
    const isOpen = ref(false);
    const focused = ref(false);

    let debounceTimer: ReturnType<typeof setTimeout> | null = null;
    let suppressWatch = false;

    const fetchSuggestions = async (term: string) => {
        if (term.length < 1) {
            suggestions.value = [];
            isOpen.value = false;
            return;
        }

        isLoading.value = true;
        try {
            const params = new URLSearchParams({ q: term });
            const response = await fetch(`/api/v1/specializations?${params}`);

            if (response.ok) {
                const data: SpecializationOption[] = await response.json();
                suggestions.value = data;
                isOpen.value = focused.value && data.length > 0;
            }
        } catch {
            suggestions.value = [];
        } finally {
            isLoading.value = false;
        }
    };

    watch(query, (newVal) => {
        if (suppressWatch) {
            suppressWatch = false;
            return;
        }
        if (debounceTimer) clearTimeout(debounceTimer);
        if (newVal.length < 1) {
            suggestions.value = [];
            isOpen.value = false;
            return;
        }
        debounceTimer = setTimeout(() => fetchSuggestions(newVal), 200);
    });

    const setQuerySilent = (value: string) => {
        suppressWatch = true;
        query.value = value;
    };

    const selectSuggestion = (suggestion: SpecializationOption) => {
        suppressWatch = true;
        query.value = suggestion.name;
        suggestions.value = [];
        isOpen.value = false;
    };

    const onFocus = () => {
        focused.value = true;
        if (suggestions.value.length > 0) {
            isOpen.value = true;
        }
    };

    const close = () => {
        focused.value = false;
        isOpen.value = false;
    };

    return {
        query,
        suggestions,
        isLoading,
        isOpen,
        selectSuggestion,
        setQuerySilent,
        onFocus,
        close,
    };
}
