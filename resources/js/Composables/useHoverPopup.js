import { onBeforeUnmount, ref } from 'vue';

/**
 * Which one of a set of marks has its details open, for charts whose details show on hover without a click. Moving to another
 * mark swaps them, and leaving waits a moment first so the pointer can cross the gap from the mark to the dialog and reach what
 * is in it (a link, a number to read).
 * @param {number} hideDelayMs How long the details stay up after the pointer leaves.
 */
export function useHoverPopup(hideDelayMs = 150) {
    /** The key of the mark whose details are open, or `null`. */
    const openKey = ref(null);

    let hideTimer;

    /** Opens the details of a mark at once, whether the pointer is on the mark or on the dialog. */
    function show(key) {
        clearTimeout(hideTimer);
        openKey.value = key;
    }

    function hideSoon() {
        clearTimeout(hideTimer);
        hideTimer = setTimeout(() => {
            openKey.value = null;
        }, hideDelayMs);
    }

    /** The dialog asked to close (a click outside it, or Escape): only the mark that is open is closed, never one that took over. */
    function close(key) {
        if (openKey.value === key) {
            openKey.value = null;
        }
    }

    onBeforeUnmount(() => clearTimeout(hideTimer));

    return { openKey, show, hideSoon, close };
}
