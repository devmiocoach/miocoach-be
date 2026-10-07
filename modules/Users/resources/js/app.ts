import { registerIcon } from '@/lib/navigation';
import IconCalendar from '~icons/lucide/calendar-days';
import IconUsers from '~icons/lucide/users';

export function setup() {
    registerIcon('coach-clients', IconUsers);
    registerIcon('coach-agenda', IconCalendar);
}

export function afterMount() {}
