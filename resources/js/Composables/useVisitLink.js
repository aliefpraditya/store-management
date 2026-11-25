import { router } from '@inertiajs/vue3';

/**
 * Click handler for an element that is a real link (`<a href>`) but should move between pages the Inertia way. A plain left
 * click stays in the app without a reload; a click with Ctrl, Cmd, Shift or Alt, a middle click, and the browser's right-click
 * menu ("Buka di tab baru") are left to the browser, which is what makes the link open in another tab.
 *
 * @param {MouseEvent} event
 * @param {string} url where the link goes
 */
export function visitUnlessNewTab(event, url) {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
    }

    event.preventDefault();
    router.visit(url);
}
