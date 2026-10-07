<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Camera, User } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface CoachData {
    display_name: string | null;
    tagline: string | null;
    bio: string | null;
    gender: string | null;
    profile_photo_url: string | null;
    cover_photo_url: string | null;
}

const props = defineProps<{
    coach: CoachData | null;
}>();

const displayName = ref(props.coach?.display_name ?? '');
const tagline = ref(props.coach?.tagline ?? '');
const bio = ref(props.coach?.bio ?? '');
const gender = ref<string>(props.coach?.gender ?? '');
const profilePhotoUrl = ref(props.coach?.profile_photo_url ?? null);
const coverPhotoUrl = ref(props.coach?.cover_photo_url ?? null);
const uploadingProfile = ref(false);
const uploadingCover = ref(false);
const photoError = ref<string | null>(null);

const MAX_PHOTO_MB = 2;
const MAX_PHOTO_BYTES = MAX_PHOTO_MB * 1024 * 1024;

const GENDERS = [
    { value: 'male', label: 'Uomo' },
    { value: 'female', label: 'Donna' },
    { value: '', label: 'Preferisco non dirlo' },
];

const page = usePage();
const errors = computed(
    () => (page.props.errors as Record<string, string>) ?? {},
);

function getData() {
    return {
        display_name: displayName.value,
        tagline: tagline.value,
        bio: bio.value,
        gender: gender.value || null,
    };
}

defineExpose({ getData });

async function handlePhotoUpload(event: Event, type: 'profile' | 'cover') {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) return;

    photoError.value = null;

    if (file.size > MAX_PHOTO_BYTES) {
        photoError.value = `La foto è troppo grande (${(file.size / 1024 / 1024).toFixed(1)}MB). Il limite massimo è ${MAX_PHOTO_MB}MB.`;
        (event.target as HTMLInputElement).value = '';
        return;
    }

    const loading = type === 'profile' ? uploadingProfile : uploadingCover;
    loading.value = true;

    const formData = new FormData();
    formData.append('photo', file);
    formData.append('type', type);

    try {
        const csrfToken = (document.cookie.match(/XSRF-TOKEN=([^;]+)/) ??
            [])[1];
        const res = await fetch(route('coach.onboarding.upload-photo'), {
            method: 'POST',
            headers: {
                'X-XSRF-TOKEN': csrfToken ? decodeURIComponent(csrfToken) : '',
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
            },
            body: formData,
        });

        if (!res.ok) {
            const data = await res.json().catch(() => ({}));
            photoError.value = data.message ?? `Errore ${res.status}. Riprova.`;
            return;
        }

        const { url } = await res.json();
        if (type === 'profile') profilePhotoUrl.value = url;
        else coverPhotoUrl.value = url;
    } catch {
        photoError.value = 'Errore durante il caricamento. Riprova.';
    } finally {
        loading.value = false;
        (event.target as HTMLInputElement).value = '';
    }
}

const taglineMax = 120;
</script>

<template>
    <div class="space-y-5">
        <!-- Main card -->
        <div
            class="rounded-3xl border border-gray-200 bg-white p-7 shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <!-- Cover photo -->
            <div
                class="relative h-40 overflow-hidden rounded-2xl border-2 border-dashed border-gray-300 bg-gradient-to-br from-gray-50 to-gray-100 dark:border-gray-700 dark:from-gray-800 dark:to-gray-900"
            >
                <img
                    v-if="coverPhotoUrl"
                    :src="coverPhotoUrl"
                    alt="Cover"
                    class="h-full w-full object-cover"
                />
                <div
                    v-else
                    class="flex h-full flex-col items-center justify-center gap-1"
                >
                    <Camera class="h-6 w-6 text-gray-400 dark:text-gray-500" />
                    <span class="text-xs text-gray-400 dark:text-gray-500"
                        >Foto di copertina</span
                    >
                </div>
                <label
                    class="absolute right-3 bottom-3 flex cursor-pointer items-center gap-1.5 rounded-full bg-black/50 px-3 py-1.5 text-xs font-medium text-white backdrop-blur-sm transition hover:bg-black/70"
                    data-testid="upload-cover-btn"
                >
                    <Camera class="h-3.5 w-3.5" />
                    <span>{{
                        uploadingCover ? 'Caricamento…' : 'Modifica'
                    }}</span>
                    <input
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        class="sr-only"
                        :disabled="uploadingCover"
                        @change="handlePhotoUpload($event, 'cover')"
                    />
                </label>
            </div>

            <!-- Avatar + info row -->
            <div class="mt-5 flex items-center gap-4">
                <div class="relative shrink-0">
                    <div
                        class="flex h-20 w-20 items-center justify-center overflow-hidden rounded-2xl bg-gray-100 ring-4 ring-white dark:bg-gray-800 dark:ring-gray-900"
                    >
                        <img
                            v-if="profilePhotoUrl"
                            :src="profilePhotoUrl"
                            alt="Foto profilo"
                            class="h-full w-full object-cover"
                        />
                        <User
                            v-else
                            class="h-9 w-9 text-gray-400 dark:text-gray-500"
                        />
                    </div>
                    <label
                        class="absolute -right-1 -bottom-1 flex h-8 w-8 cursor-pointer items-center justify-center rounded-full bg-teal-600 text-white shadow-lg transition hover:bg-teal-500"
                        data-testid="upload-profile-btn"
                    >
                        <Camera class="h-3.5 w-3.5" />
                        <input
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="sr-only"
                            :disabled="uploadingProfile"
                            @change="handlePhotoUpload($event, 'profile')"
                        />
                    </label>
                </div>
                <div>
                    <p
                        class="text-sm font-semibold text-gray-900 dark:text-white"
                    >
                        Foto Profilo
                    </p>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        JPG, PNG o WebP. Max {{ MAX_PHOTO_MB }}MB.
                    </p>
                </div>
            </div>

            <p
                v-if="photoError"
                class="mt-4 rounded-lg bg-red-50 px-4 py-2.5 text-sm text-red-600 dark:bg-red-900/20 dark:text-red-400"
                data-testid="photo-upload-error"
            >
                {{ photoError }}
            </p>

            <!-- Divider -->
            <hr class="my-6 border-gray-200 dark:border-gray-800" />

            <!-- Form fields -->
            <div class="space-y-5">
                <!-- Name -->
                <div class="space-y-1.5">
                    <label
                        class="block text-[10px] font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400"
                        for="display_name"
                    >
                        Nome visualizzato *
                    </label>
                    <input
                        id="display_name"
                        v-model="displayName"
                        type="text"
                        placeholder="Es: Marco Rossi"
                        data-testid="input-display-name"
                        class="h-11 w-full rounded-lg border border-gray-200 bg-white px-4 text-gray-900 transition placeholder:text-gray-400 focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder:text-gray-600 dark:focus:border-teal-400 dark:focus:ring-teal-400/20"
                        :class="
                            errors.display_name
                                ? 'border-red-400 dark:border-red-500'
                                : ''
                        "
                    />
                    <p
                        v-if="errors.display_name"
                        class="mt-1 text-xs text-red-500 dark:text-red-400"
                        data-testid="error-display-name"
                    >
                        {{ errors.display_name }}
                    </p>
                </div>

                <!-- Tagline -->
                <div class="space-y-1.5">
                    <label
                        class="block text-[10px] font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400"
                        for="tagline"
                    >
                        Tagline *
                    </label>
                    <input
                        id="tagline"
                        v-model="tagline"
                        type="text"
                        placeholder="Es: Personal trainer specializzato in HIIT e Functional training"
                        :maxlength="taglineMax"
                        data-testid="input-tagline"
                        class="h-11 w-full rounded-lg border border-gray-200 bg-white px-4 text-gray-900 transition placeholder:text-gray-400 focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder:text-gray-600 dark:focus:border-teal-400 dark:focus:ring-teal-400/20"
                        :class="
                            errors.tagline
                                ? 'border-red-400 dark:border-red-500'
                                : ''
                        "
                    />
                    <p
                        v-if="errors.tagline"
                        class="mt-1 text-xs text-red-500 dark:text-red-400"
                        data-testid="error-tagline"
                    >
                        {{ errors.tagline }}
                    </p>
                    <div class="flex justify-end">
                        <span
                            class="text-xs text-gray-400"
                            :class="
                                tagline.length >= taglineMax
                                    ? '!text-red-500'
                                    : ''
                            "
                        >
                            {{ tagline.length }} / {{ taglineMax }}
                        </span>
                    </div>
                </div>

                <!-- Gender -->
                <div class="space-y-1.5">
                    <label
                        class="block text-[10px] font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400"
                    >
                        Genere
                    </label>
                    <div class="grid grid-cols-3 gap-3">
                        <button
                            v-for="g in GENDERS"
                            :key="g.value"
                            type="button"
                            :data-testid="`gender-${g.value}`"
                            :class="[
                                'rounded-xl border px-3 py-3 text-sm font-semibold transition-all',
                                gender === g.value
                                    ? 'border-teal-600 bg-teal-50 text-teal-600 shadow-[0_0_0_4px_rgba(13,148,136,0.1)] dark:border-teal-400 dark:bg-teal-900/20 dark:text-teal-400'
                                    : 'border-gray-200 bg-white text-gray-500 hover:border-teal-400/40 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400',
                            ]"
                            @click="gender = g.value"
                        >
                            {{ g.label }}
                        </button>
                    </div>
                </div>

                <!-- Bio -->
                <div class="space-y-1.5">
                    <label
                        class="block text-[10px] font-semibold tracking-wider text-gray-500 uppercase dark:text-gray-400"
                        for="bio"
                    >
                        Bio Professionale *
                    </label>
                    <textarea
                        id="bio"
                        v-model="bio"
                        rows="4"
                        placeholder="Descrivi il tuo approccio al coaching e cosa ti rende unico…"
                        maxlength="500"
                        data-testid="input-bio"
                        class="w-full resize-none rounded-lg border border-gray-200 bg-white px-4 py-3 text-gray-900 transition placeholder:text-gray-400 focus:border-teal-500 focus:ring-2 focus:ring-teal-500/20 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:placeholder:text-gray-600 dark:focus:border-teal-400 dark:focus:ring-teal-400/20"
                        :class="
                            errors.bio
                                ? 'border-red-400 dark:border-red-500'
                                : ''
                        "
                    />
                    <p
                        v-if="errors.bio"
                        class="mt-1 text-xs text-red-500 dark:text-red-400"
                        data-testid="error-bio"
                    >
                        {{ errors.bio }}
                    </p>
                    <div class="flex items-center justify-between">
                        <span
                            class="text-xs text-gray-400"
                            :class="
                                errors.bio
                                    ? '!text-red-500 dark:!text-red-400'
                                    : ''
                            "
                            >Minimo 10 caratteri</span
                        >
                        <span class="text-xs text-gray-400"
                            >{{ bio.length }} / 500</span
                        >
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
