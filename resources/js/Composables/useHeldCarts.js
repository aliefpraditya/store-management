import { computed, ref } from 'vue';
import { newUuid } from './useCart';

/**
 * Carts that were put on hold so the queue can move on: the customer forgot a wallet, or waits for someone. They are kept
 * in localStorage per shift, like the cart itself, and are gone when the browser's storage is cleared.
 *
 * @param {import('vue').Ref<number|null>} shiftId
 */
export function useHeldCarts(shiftId) {
    const storageKey = computed(() => `mitra.held-carts.${shiftId.value}`);

    /** @type {import('vue').Ref<{id: string, heldAt: string, lines: object[]}[]>} */
    const held = ref(restore());

    function restore() {
        try {
            return JSON.parse(window.localStorage.getItem(storageKey.value) ?? '[]');
        } catch {
            return [];
        }
    }

    function persist() {
        try {
            window.localStorage.setItem(storageKey.value, JSON.stringify(held.value));
        } catch {
            // Storage can be blocked; held carts then only live until the page reloads.
        }
    }

    /**
     * Puts the lines aside, newest first.
     * @param {object[]} lines the cart's lines
     */
    function hold(lines) {
        held.value = [{ id: newUuid(), heldAt: new Date().toISOString(), lines: JSON.parse(JSON.stringify(lines)) }, ...held.value];
        persist();
    }

    /**
     * Takes a held cart out of the list and answers its lines.
     * @param {string} id
     * @returns {object[]}
     */
    function take(id) {
        const heldCart = held.value.find((candidate) => candidate.id === id);

        held.value = held.value.filter((candidate) => candidate.id !== id);
        persist();

        return heldCart?.lines ?? [];
    }

    function discard(id) {
        held.value = held.value.filter((candidate) => candidate.id !== id);
        persist();
    }

    return { held, hold, take, discard };
}
