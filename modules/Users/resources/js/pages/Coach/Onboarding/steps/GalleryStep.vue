<script setup lang="ts">
import { Camera, Film, Play, Trash2, Upload } from 'lucide-vue-next';
import { ref } from 'vue';

interface CoachData {
    gallery_photos: string[] | null;
    intro_video_url: string | null;
}

const props = defineProps<{
    coach: CoachData | null;
}>();

const MAX_PHOTOS = 6;
const MAX_VIDEO_MB = 100;
const MAX_PHOTO_MB = 2;

const photos = ref<
    Array<{ url: string | null; file: File | null; preview: string | null }>
>(
    (props.coach?.gallery_photos ?? []).map((url) => ({
        url,
        file: null,
        preview: url,
    })),
);
const videoFile = ref<File | null>(null);
const videoPreview = ref<string | null>(props.coach?.intro_video_url ?? null);

function handlePhotoUpload(event: Event) {
    const files = (event.target as HTMLInputElement).files;
    if (!files) return;

    for (const file of Array.from(files)) {
        if (photos.value.length >= MAX_PHOTOS) break;
        if (file.size > MAX_PHOTO_MB * 1024 * 1024) {
            alert(
                `Foto troppo grande (${(file.size / 1024 / 1024).toFixed(1)}MB). Massimo ${MAX_PHOTO_MB}MB.`,
            );
            continue;
        }
        const preview = URL.createObjectURL(file);
        photos.value.push({ url: null, file, preview });
    }
    (event.target as HTMLInputElement).value = '';
}

function removePhoto(index: number) {
    const photo = photos.value[index];
    if (photo.preview && !photo.url) URL.revokeObjectURL(photo.preview);
    photos.value.splice(index, 1);
}

function handleVideoUpload(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) return;

    if (file.size > MAX_VIDEO_MB * 1024 * 1024) {
        alert(`Video troppo grande. Massimo ${MAX_VIDEO_MB}MB.`);
        return;
    }

    const video = document.createElement('video');
    video.preload = 'metadata';
    video.onloadedmetadata = () => {
        URL.revokeObjectURL(video.src);
        if (video.duration > 30) {
            alert(
                `Video troppo lungo (${Math.ceil(video.duration)}s). Massimo 30 secondi.`,
            );
            (event.target as HTMLInputElement).value = '';
            return;
        }
        videoFile.value = file;
        videoPreview.value = URL.createObjectURL(file);
    };
    video.src = URL.createObjectURL(file);
    (event.target as HTMLInputElement).value = '';
}

function removeVideo() {
    if (videoPreview.value && videoFile.value)
        URL.revokeObjectURL(videoPreview.value);
    videoFile.value = null;
    videoPreview.value = null;
}

function getData() {
    return {
        gallery_photos: photos.value.map((p) => p.url).filter(Boolean),
        has_video: !!videoPreview.value,
    };
}

function getFiles(): Array<{ key: string; file: File }> {
    const files: Array<{ key: string; file: File }> = [];
    photos.value.forEach((p, i) => {
        if (p.file) files.push({ key: `gallery_photo_${i}`, file: p.file });
    });
    if (videoFile.value)
        files.push({ key: 'intro_video', file: videoFile.value });
    return files;
}

defineExpose({ getData, getFiles });
</script>

<template>
    <div class="space-y-5">
        <!-- Gallery photos card -->
        <div
            class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <div
                class="mb-1 flex items-center gap-2 text-lg font-semibold text-gray-900 dark:text-white"
            >
                <Camera class="h-5 w-5 text-teal-600 dark:text-teal-400" />
                Gallery foto
            </div>
            <p class="mb-5 text-sm text-gray-500 dark:text-gray-400">
                {{ photos.length }} / {{ MAX_PHOTOS }} caricate
            </p>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                <div
                    v-for="(photo, index) in photos"
                    :key="index"
                    class="group relative aspect-square overflow-hidden rounded-2xl border border-gray-200 dark:border-gray-700"
                    :data-testid="`gallery-photo-${index}`"
                >
                    <img
                        v-if="photo.preview"
                        :src="photo.preview"
                        alt=""
                        class="h-full w-full object-cover"
                    />
                    <div
                        v-else
                        class="flex h-full items-center justify-center bg-gray-100 dark:bg-gray-800"
                    >
                        <Camera class="h-8 w-8 text-gray-300" />
                    </div>

                    <div
                        v-if="index === 0"
                        class="absolute top-2 left-2 rounded-full bg-gradient-to-r from-teal-600 to-teal-400 px-2 py-0.5 text-[10px] font-semibold text-white"
                    >
                        COVER
                    </div>

                    <button
                        :data-testid="`remove-photo-${index}`"
                        class="absolute top-2 right-2 hidden h-7 w-7 items-center justify-center rounded-full bg-white/80 text-red-500 backdrop-blur transition group-hover:flex hover:bg-red-500 hover:text-white dark:bg-gray-900/80"
                        @click="removePhoto(index)"
                    >
                        <Trash2 class="h-3.5 w-3.5" />
                    </button>
                </div>

                <label
                    v-for="i in Math.max(0, MAX_PHOTOS - photos.length)"
                    :key="`slot-${i}`"
                    class="group flex aspect-square cursor-pointer flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-gray-200 bg-gray-50 text-gray-400 transition hover:border-teal-400 hover:text-teal-600 dark:border-gray-700 dark:bg-gray-800"
                    data-testid="upload-photo-slot"
                >
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-full bg-white transition group-hover:bg-teal-100 dark:bg-gray-700"
                    >
                        <Upload class="h-4 w-4" />
                    </div>
                    <span class="text-xs font-medium">Carica foto</span>
                    <input
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        multiple
                        class="sr-only"
                        @change="handlePhotoUpload"
                    />
                </label>
            </div>
        </div>

        <!-- Video card -->
        <div
            class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <div
                class="mb-1 flex items-center gap-2 text-lg font-semibold text-gray-900 dark:text-white"
            >
                <Film class="h-5 w-5 text-teal-600 dark:text-teal-400" />
                Video presentazione
                <span
                    class="rounded-full bg-teal-100 px-2 py-0.5 text-[10px] font-bold text-teal-700 dark:bg-teal-900/40 dark:text-teal-300"
                >
                    +3× conversione
                </span>
            </div>
            <p class="mb-5 text-sm text-gray-500 dark:text-gray-400">
                Massimo 30 secondi · MP4, MOV · {{ MAX_VIDEO_MB }} MB
            </p>

            <div
                v-if="videoPreview"
                class="relative aspect-video overflow-hidden rounded-2xl bg-gray-900"
            >
                <video :src="videoPreview" class="h-full w-full object-cover" />
                <button
                    class="absolute top-1/2 left-1/2 flex h-16 w-16 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-teal-600 shadow-2xl"
                >
                    <Play class="h-7 w-7 translate-x-0.5" fill="currentColor" />
                </button>
                <button
                    data-testid="remove-video"
                    class="absolute top-3 right-3 flex h-8 w-8 items-center justify-center rounded-full bg-white/90 text-red-500 backdrop-blur transition hover:bg-red-500 hover:text-white"
                    @click="removeVideo"
                >
                    <Trash2 class="h-4 w-4" />
                </button>
            </div>

            <label
                v-else
                class="group flex aspect-video w-full cursor-pointer flex-col items-center justify-center gap-3 rounded-2xl border-2 border-dashed border-gray-200 bg-gray-50 text-gray-400 transition hover:border-teal-400 hover:text-teal-600 dark:border-gray-700 dark:bg-gray-800"
                data-testid="upload-video-area"
            >
                <div
                    class="flex h-14 w-14 items-center justify-center rounded-full bg-gradient-to-r from-teal-600 to-teal-400 text-white shadow-lg"
                >
                    <Film class="h-6 w-6" />
                </div>
                <div class="text-center">
                    <p
                        class="text-sm font-semibold text-gray-700 dark:text-gray-300"
                    >
                        Carica il tuo video intro
                    </p>
                    <p class="mt-0.5 text-xs">o trascinalo qui · max 30s</p>
                </div>
                <input
                    type="file"
                    accept="video/mp4,video/quicktime"
                    class="sr-only"
                    @change="handleVideoUpload"
                />
            </label>

            <div
                class="mt-4 rounded-xl border border-teal-100 bg-teal-50/50 p-3 text-xs leading-relaxed text-gray-600 dark:border-teal-900 dark:bg-teal-900/20 dark:text-gray-400"
            >
                <span class="font-semibold text-teal-700 dark:text-teal-300"
                    >Suggerimento script:</span
                >
                "Ciao, sono [nome]. Aiuto persone come te a [obiettivo] in
                [tempo]. Iniziamo con una sessione conoscitiva gratuita."
            </div>
        </div>
    </div>
</template>
