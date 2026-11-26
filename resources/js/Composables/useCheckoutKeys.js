import { computed } from 'vue';
import { newUuid } from './useCart';

/**
 * The codes (`client_uuid`) of the receipt and of the order a checkout makes, kept in the browser's storage with the cart.
 *
 * The code is what lets the server answer a second "Bayar" of the same checkout with the receipt it already made instead of
 * selling twice. Held only in the memory of the page it was lost whenever the page was loaded again, while the cart stayed:
 * a cashier whose screen reloaded after the receipt was made saw the cart still full, rang it up again, and it was sold twice.
 * The codes are therefore stored with the cart and forgotten only once a checkout has succeeded.
 *
 * They belong to the contents they were made for: when the cart changes, the next checkout is a different one and gets codes
 * of its own.
 *
 * @param {import('vue').Ref<number|null>} shiftId
 */
export function useCheckoutKeys(shiftId) {
    const storageKey = computed(() => `mitra.checkout.${shiftId.value}`);

    /** @returns {{contents: string, saleUuid: string, orderUuid: string}|null} */
    function read() {
        try {
            return JSON.parse(window.localStorage.getItem(storageKey.value));
        } catch {
            return null;
        }
    }

    /** @param {{contents: string, saleUuid: string, orderUuid: string}} keys */
    function write(keys) {
        try {
            window.localStorage.setItem(storageKey.value, JSON.stringify(keys));
        } catch {
            // Storage can be blocked; the codes then only live until the page reloads, as they did before.
        }
    }

    /**
     * The codes of the checkout of these contents: the ones kept from an earlier try with the same contents, or new ones.
     *
     * @param {string} contents what the checkout sells, as text, e.g. the cart lines as JSON
     * @returns {{saleUuid: string, orderUuid: string}}
     */
    function keysFor(contents) {
        const kept = read();

        if (kept?.contents === contents && kept.saleUuid && kept.orderUuid) {
            return kept;
        }

        const keys = { contents, saleUuid: newUuid(), orderUuid: newUuid() };
        write(keys);

        return keys;
    }

    /** A checkout has succeeded, so the next one is a new one. */
    function forget() {
        try {
            window.localStorage.removeItem(storageKey.value);
        } catch {
            // Nothing to forget when storage is blocked.
        }
    }

    return { keysFor, forget };
}
