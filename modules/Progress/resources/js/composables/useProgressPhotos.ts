import { useHttp } from '@inertiajs/vue3';
import { ref } from 'vue';

import type { PhotoPose, ProgressPhoto } from '../types';

export function useProgressPhotos(clientId: number) {
    const photos = ref<ProgressPhoto[]>([]);
    const loading = ref(false);

    async function fetchPhotos() {
        loading.value = true;
        try {
            const http = useHttp({});
            const response = await http.get(
                route('coach.clients.progress.photos.index', {
                    client: clientId,
                }),
            );
            photos.value = response?.data ?? [];
        } catch {
            photos.value = [];
        } finally {
            loading.value = false;
        }
    }

    async function uploadPhoto(data: {
        photo: File;
        pose?: PhotoPose;
        taken_at: string;
        notes?: string;
    }) {
        const http = useHttp({
            photo: data.photo,
            taken_at: data.taken_at,
            pose: data.pose ?? null,
            notes: data.notes ?? null,
        });
        await http.post(
            route('coach.clients.progress.photos.store', {
                client: clientId,
            }),
        );
        await fetchPhotos();
    }

    async function deletePhoto(photoId: number) {
        const http = useHttp({});
        await http.delete(
            route('coach.clients.progress.photos.destroy', {
                client: clientId,
                photo: photoId,
            }),
        );
        await fetchPhotos();
    }

    fetchPhotos();

    return {
        photos,
        loading,
        fetchPhotos,
        uploadPhoto,
        deletePhoto,
    };
}
