// import { registerIcon } from '@/lib/navigation';
// import IconExample from '~icons/lucide/example';

import '../css/style.css';

/**
 * CoachCommerce module setup
 * Called during app initialization before mounting
 */
export function setup() {
    console.debug('CoachCommerce module loaded');

    // Register icons for navigation items defined in routes/navigation.php
    // registerIcon('coach-commerce', IconExample);
}

/**
 * CoachCommerce module after mount logic
 * Called after the app has been mounted
 */
export function afterMount() {
    console.debug('CoachCommerce module after mount logic executed');
}
