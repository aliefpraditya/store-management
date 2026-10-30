import { computed, ref, watch } from 'vue';

/**
 * Rounds money to cents the way the server does (half up), so the screen total matches the receipt.
 * @param {number} amount
 * @returns {number}
 */
export function roundMoney(amount) {
    return Math.round((amount + Number.EPSILON) * 100) / 100;
}

/**
 * A random v4 UUID. `crypto.randomUUID` only exists on secure origins, and a store may run on plain http.
 * @returns {string}
 */
export function newUuid() {
    const bytes = crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = [...bytes].map((byte) => byte.toString(16).padStart(2, '0')).join('');

    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

/**
 * The cashier's cart. Each line is one product in one unit; prices and conversions are copied from the item lookup
 * and only used to show totals, because the server prices the sale again. The cart is kept in localStorage per shift
 * so a refresh does not lose it.
 *
 * @param {import('vue').Ref<number|null>} shiftId
 */
export function useCart(shiftId) {
    const storageKey = computed(() => `mitra.cart.${shiftId.value}`);

    /** @type {import('vue').Ref<{key: string, product: object, unitId: number, quantity: number|string}[]>} */
    const lines = ref(restore());

    function restore() {
        try {
            return JSON.parse(window.localStorage.getItem(storageKey.value) ?? '[]');
        } catch {
            return [];
        }
    }

    watch(
        lines,
        (value) => {
            try {
                window.localStorage.setItem(storageKey.value, JSON.stringify(value));
            } catch {
                // Storage can be blocked; the cart then only lives until the page reloads.
            }
        },
        { deep: true },
    );

    const unitOf = (line) => line.product.units.find((unit) => unit.id === line.unitId);

    const quantityOf = (line) => Number(line.quantity) || 0;

    const priceOf = (line) => Number(unitOf(line).price);

    const subtotalOf = (line) => roundMoney(quantityOf(line) * priceOf(line));

    const total = computed(() => roundMoney(lines.value.reduce((sum, line) => sum + subtotalOf(line), 0)));

    const isEmpty = computed(() => lines.value.length === 0);

    /**
     * Base-unit quantity of a product across all its lines, since the same item can be in the cart in two units.
     * @returns {number}
     */
    function baseQuantityOf(productId) {
        return lines.value
            .filter((line) => line.product.id === productId)
            .reduce((sum, line) => sum + quantityOf(line) * Number(unitOf(line).conversion_factor), 0);
    }

    /** True when the cart asks for more of the product than the register's warehouse holds. */
    function isOverStock(line) {
        return Math.round(baseQuantityOf(line.product.id) * 1000) / 1000 > Number(line.product.stock);
    }

    const hasOverStock = computed(() => lines.value.some(isOverStock));

    /**
     * Adds one of the unit; the same item and unit already in the cart just gets one more.
     * @param {object} product an item from the lookup
     * @param {number} unitId
     */
    function add(product, unitId) {
        const existing = lines.value.find((line) => line.product.id === product.id && line.unitId === unitId);

        if (existing) {
            existing.quantity = quantityOf(existing) + 1;

            return;
        }

        lines.value.push({ key: newUuid(), product, unitId, quantity: 1 });
    }

    function remove(line) {
        lines.value = lines.value.filter((candidate) => candidate.key !== line.key);
    }

    function clear() {
        lines.value = [];
    }

    /** The lines as the sale request wants them. */
    const payload = computed(() => lines.value.map((line) => ({ product_unit_id: line.unitId, quantity: quantityOf(line) })));

    return { lines, total, isEmpty, hasOverStock, payload, unitOf, quantityOf, priceOf, subtotalOf, isOverStock, add, remove, clear };
}
