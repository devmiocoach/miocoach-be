import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';

interface UndoPayload {
    bookingId: number;
    previousStartsAt: string;
    previousEndsAt: string;
}

export function useAgendaUndo() {
    function showUndoToast(payload: UndoPayload, message: string) {
        toast(message, {
            duration: 8000,
            action: {
                label: 'Undo',
                onClick: () => {
                    router.patch(
                        route(
                            'coach.agenda.bookings.update',
                            payload.bookingId,
                        ),
                        {
                            starts_at: payload.previousStartsAt,
                            ends_at: payload.previousEndsAt,
                        },
                        { preserveScroll: true },
                    );
                },
            },
        });
    }

    function recordMove(
        bookingId: number,
        previousStartsAt: string,
        previousEndsAt: string,
    ) {
        showUndoToast(
            { bookingId, previousStartsAt, previousEndsAt },
            'Session moved',
        );
    }

    function recordResize(
        bookingId: number,
        previousStartsAt: string,
        previousEndsAt: string,
    ) {
        showUndoToast(
            { bookingId, previousStartsAt, previousEndsAt },
            'Session resized',
        );
    }

    return { recordMove, recordResize };
}
