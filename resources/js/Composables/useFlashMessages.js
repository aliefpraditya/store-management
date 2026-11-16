import { onBeforeUnmount } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useQuasar } from 'quasar';

/** The same message twice within this time is one visit seen twice (the page mounting, then the visit finishing). */
const SAME_VISIT_WINDOW_MS = 500;

/** When each message was last shown, by type and text. */
const lastShownAt = new Map();

/**
 * @param {string} key
 * @returns {boolean} true when this message was just shown for the same visit
 */
function wasJustShown(key) {
    const now = Date.now();
    const isRepeat = now - (lastShownAt.get(key) ?? 0) < SAME_VISIT_WINDOW_MS;

    lastShownAt.set(key, now);

    return isRepeat;
}

/**
 * Shows the `success` and `error` messages the server flashes after a redirect as toasts. Call it once in a layout.
 *
 * It reacts to every finished visit, not to a change of the message: a cashier who retries a payment and gets the same
 * refusal twice must see it twice, and a watcher on the text would stay silent the second time.
 */
export function useFlashMessages() {
    const page = usePage();
    const $q = useQuasar();

    function show(flash) {
        if (flash?.success && !wasJustShown(`success:${flash.success}`)) {
            $q.notify({ type: 'positive', message: flash.success, position: 'top' });
        }

        if (flash?.error && !wasJustShown(`error:${flash.error}`)) {
            $q.notify({
                type: 'negative',
                message: flash.error,
                position: 'top',
                multiLine: true,
                timeout: 10000,
                actions: [{ icon: 'close', color: 'white', round: true, dense: true }],
            });
        }
    }

    show(page.props.flash);

    const stopListening = router.on('success', (event) => show(event.detail.page.props.flash));

    onBeforeUnmount(stopListening);
}
