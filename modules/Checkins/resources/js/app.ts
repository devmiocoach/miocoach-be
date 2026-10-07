// import { registerIcon } from '@/lib/navigation';
// import IconExample from '~icons/lucide/example';

import '../css/style.css';

/**
 * Checkins module setup
 * Called during app initialization before mounting
 */
export function setup() {
    console.debug('Checkins module loaded');

    // Register icons for navigation items defined in routes/navigation.php
    // registerIcon('checkins', IconExample);
}

/**
 * Checkins module after mount logic
 * Called after the app has been mounted
 */
export function afterMount() {
    console.debug('Checkins module after mount logic executed');
}
