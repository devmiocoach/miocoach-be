// import { registerIcon } from '@/lib/navigation';
// import IconExample from '~icons/lucide/example';

import '../css/style.css';

/**
 * Bookings module setup
 * Called during app initialization before mounting
 */
export function setup() {
    console.debug('Bookings module loaded');

    // Register icons for navigation items defined in routes/navigation.php
    // registerIcon('bookings', IconExample);
}

/**
 * Bookings module after mount logic
 * Called after the app has been mounted
 */
export function afterMount() {
    console.debug('Bookings module after mount logic executed');
}
