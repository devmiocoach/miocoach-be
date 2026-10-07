import { registerIcon } from '@/lib/navigation';
import IconMessage from '~icons/lucide/message-circle';

import '../css/style.css';

/**
 * Chat module setup
 * Called during app initialization before mounting
 */
export function setup() {
    // Register icons for navigation items defined in routes/navigation.php
    registerIcon('chat', IconMessage);
}

/**
 * Chat module after mount logic
 * Called after the app has been mounted
 */
export function afterMount() {}
