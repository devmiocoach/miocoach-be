import { ref, watch } from 'vue';
import type { NominatimSuggestion } from '../types/public';

export function useCityAutocomplete() {
    const query = ref('');
    const suggestions = ref<NominatimSuggestion[]>([]);
    const isLoading = ref(false);
    const isOpen = ref(false);
    const focused = ref(false);

    let debounceTimer: ReturnType<typeof setTimeout> | null = null;
    let suppressWatch = false;

    const fetchSuggestions = async (term: string) => {
        if (term.length < 2) {
            suggestions.value = [];
            isOpen.value = false;
            return;
        }

        isLoading.value = true;
        try {
            const params = new URLSearchParams({
                format: 'json',
                countrycodes: 'it',
                q: term,
                limit: '5',
                addressdetails: '1',
                'accept-language': 'it',
            });

            const response = await fetch(
                `https://nominatim.openstreetmap.org/search?${params}`,
                {
                    headers: {
                        'User-Agent': 'MioCoach/1.0 (contact@miocoach.dev)',
                    },
                },
            );

            if (response.ok) {
                const data: NominatimSuggestion[] = await response.json();
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
        if (newVal.length < 2) {
            suggestions.value = [];
            isOpen.value = false;
            return;
        }
        debounceTimer = setTimeout(() => fetchSuggestions(newVal), 300);
    });

    const setQuerySilent = (value: string) => {
        suppressWatch = true;
        query.value = value;
    };

    const selectSuggestion = (suggestion: NominatimSuggestion) => {
        const city =
            suggestion.address?.city ||
            suggestion.address?.town ||
            suggestion.address?.village ||
            suggestion.address?.municipality ||
            suggestion.name;
        suppressWatch = true;
        query.value = city;
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
