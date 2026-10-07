import { registerIcon } from '@/lib/navigation';
import IconClipboardDocumentList from '~icons/heroicons/clipboard-document-list';
import IconRectangleStack from '~icons/heroicons/rectangle-stack';

import '../css/style.css';

/**
 * WorkoutPlans module setup
 * Called during app initialization before mounting
 */
export function setup() {
    console.debug('WorkoutPlans module loaded');

    // Register icons for navigation items defined in routes/navigation.php
    registerIcon('workoutplans', IconClipboardDocumentList);
    registerIcon('exercises', IconRectangleStack);
}

/**
 * WorkoutPlans module after mount logic
 * Called after the app has been mounted
 */
export function afterMount() {
    console.debug('WorkoutPlans module after mount logic executed');
}
