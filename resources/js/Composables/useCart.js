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
        (cartLines) => {
            try {
                window.localStorage.setItem(storageKey.value, JSON.stringify(cartLines));
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

    const isTotalOverStock = (line) => Math.round(baseQuantityOf(line.product.id) * 1000) / 1000 > Number(line.product.stock);

    /**
     * Takes the product's lines off its shelf in cart order, the way the server does: a sealed pack (a roll) takes whole packs,
     * anything smaller takes loose pieces first and opens a pack only when they run out.
     * @returns {number|null} the line that cannot be served from the whole packs left, or null when they all can
     */
    function firstLineShortOfSealedPacks(productId) {
        const productLines = lines.value.filter((line) => line.product.id === productId);
        const product = productLines[0].product;
        const sealedPackUnit = product.units.find((unit) => unit.is_sealed_pack);

        if (!sealedPackUnit || product.intact_packs === null || product.intact_packs === undefined) {
            return null;
        }

        const packSize = Number(sealedPackUnit.conversion_factor);
        let quantity = Number(product.stock);
        let intactPacks = product.intact_packs;

        for (const line of productLines) {
            const lineQuantity = quantityOf(line);

            if (unitOf(line).is_sealed_pack) {
                if (lineQuantity > intactPacks || !Number.isInteger(lineQuantity)) {
                    return line.key;
                }

                intactPacks -= lineQuantity;
                quantity -= lineQuantity * packSize;

                continue;
            }

            const baseQuantity = lineQuantity * Number(unitOf(line).conversion_factor);
            const shortfall = baseQuantity - (quantity - intactPacks * packSize);
            const packsToOpen = shortfall > 1e-9 ? Math.ceil(shortfall / packSize - 1e-9) : 0;

            if (packsToOpen > intactPacks) {
                return line.key;
            }

            intactPacks -= packsToOpen;
            quantity -= baseQuantity;
        }

        return null;
    }

    const isShortOfSealedPacks = (line) => firstLineShortOfSealedPacks(line.product.id) === line.key;

    /** True when the cart asks for more of the product than the register's warehouse holds, in metres or in whole rolls. */
    function isOverStock(line) {
        return isTotalOverStock(line) || isShortOfSealedPacks(line);
    }

    /**
     * What is wrong with a line that {@see isOverStock} flags, in words for the cashier; the screen adds what is left after it.
     * @returns {string}
     */
    function stockWarningOf(line) {
        if (!isShortOfSealedPacks(line)) {
            return 'Stok tersisa';
        }

        const packCode = line.product.units.find((unit) => unit.is_sealed_pack).code;

        if (unitOf(line).is_sealed_pack && !Number.isInteger(quantityOf(line))) {
            return `${packCode} harus utuh (bilangan bulat). Tersisa`;
        }

        return `${packCode} utuh tidak cukup. Tersisa`;
    }

    const hasOverStock = computed(() => lines.value.some(isOverStock));

    const roundQuantity = (quantity) => Math.round((quantity + Number.EPSILON) * 1000) / 1000;

    /**
     * How one cart line divides between what the shelf can serve now and what has to be ordered, taking the product's stock
     * in cart order the way the server does: a sealed pack (a roll) takes whole packs, anything smaller takes loose pieces
     * first and opens a pack only when they run out. `shelf` is the product's remaining stock and is updated in place.
     * @param {object} line
     * @param {{quantity: number, intactPacks: number|null, packSize: number|null}} shelf
     * @returns {number} the quantity of the line (in the line's own unit) that is in stock
     */
    function inStockQuantityOf(line, shelf) {
        const lineQuantity = quantityOf(line);
        const conversionFactor = Number(unitOf(line).conversion_factor);
        const hasPacks = shelf.intactPacks !== null && shelf.packSize !== null;

        if (hasPacks && unitOf(line).is_sealed_pack) {
            const packsSold = Math.max(0, Math.min(Math.floor(lineQuantity), shelf.intactPacks));

            shelf.intactPacks -= packsSold;
            shelf.quantity -= packsSold * shelf.packSize;

            return packsSold;
        }

        const baseSold = Math.max(0, Math.min(lineQuantity * conversionFactor, shelf.quantity));

        if (hasPacks) {
            const loose = shelf.quantity - shelf.intactPacks * shelf.packSize;
            const packsOpened = baseSold > loose + 1e-9 ? Math.ceil((baseSold - loose) / shelf.packSize - 1e-9) : 0;

            shelf.intactPacks -= Math.min(packsOpened, shelf.intactPacks);
        }

        shelf.quantity -= baseSold;

        return Math.floor((baseSold / conversionFactor) * 1000 + 1e-6) / 1000;
    }

    /**
     * The cart divided into what the shelf can serve (a receipt) and what is short (a customer order, bought first and
     * collected later). The server checks the receipt part against the real stock again.
     * @returns {{saleLines: object[], orderLines: object[], saleTotal: number, orderTotal: number}} lines with `line`, `quantity` and `subtotal`
     */
    const split = computed(() => {
        const shelves = new Map();
        const saleLines = [];
        const orderLines = [];

        for (const line of lines.value) {
            const product = line.product;
            const sealedPackUnit = product.units.find((unit) => unit.is_sealed_pack);

            if (!shelves.has(product.id)) {
                const hasPacks = sealedPackUnit && product.intact_packs !== null && product.intact_packs !== undefined;

                shelves.set(product.id, { quantity: Number(product.stock), intactPacks: hasPacks ? product.intact_packs : null, packSize: hasPacks ? Number(sealedPackUnit.conversion_factor) : null });
            }

            const inStock = roundQuantity(inStockQuantityOf(line, shelves.get(product.id)));
            const short = roundQuantity(quantityOf(line) - inStock);

            if (inStock > 0) {
                saleLines.push({ line, quantity: inStock, subtotal: roundMoney(inStock * priceOf(line)) });
            }

            if (short > 0) {
                orderLines.push({ line, quantity: short, subtotal: roundMoney(short * priceOf(line)) });
            }
        }

        const sumOf = (splitLines) => roundMoney(splitLines.reduce((sum, splitLine) => sum + splitLine.subtotal, 0));

        return { saleLines, orderLines, saleTotal: sumOf(saleLines), orderTotal: sumOf(orderLines) };
    });

    const toPayload = (splitLines) => splitLines.map(({ line, quantity }) => ({ product_unit_id: line.unitId, quantity }));

    /**
     * Adds the quantity of the unit (one unless told otherwise); the same item and unit already in the cart just grows by it.
     * @param {object} product an item from the lookup
     * @param {number} unitId
     * @param {number} [quantity]
     * @returns {object} the line that was added or increased, so the screen can show which one it was
     */
    function add(product, unitId, quantity = 1) {
        const existingLine = lines.value.find((line) => line.product.id === product.id && line.unitId === unitId);

        if (existingLine) {
            existingLine.quantity = roundQuantity(quantityOf(existingLine) + quantity);

            return existingLine;
        }

        lines.value.push({ key: newUuid(), product, unitId, quantity });

        return lines.value[lines.value.length - 1];
    }

    /**
     * Brings the cart up to date with the server after it was kept across a refresh: prices, units and stock are taken
     * from fresh items, and a line whose item is gone (inactive, or that unit is no longer sold) is dropped.
     *
     * @param {(unitIds: number[]) => Promise<object[]>} fetchItemsFor the lookup of the units' current items
     * @returns {Promise<{changed: object[], removed: object[]}>} the lines whose price moved, and the lines dropped
     */
    async function refresh(fetchItemsFor) {
        if (lines.value.length === 0) {
            return { changed: [], removed: [] };
        }

        const freshItems = await fetchItemsFor(lines.value.map((line) => line.unitId));
        const freshItemById = new Map(freshItems.map((item) => [item.id, item]));
        const changed = [];
        const removed = [];

        lines.value = lines.value.filter((line) => {
            const freshItem = freshItemById.get(line.product.id);

            if (!freshItem || !freshItem.units.some((unit) => unit.id === line.unitId)) {
                removed.push(line);

                return false;
            }

            const priceBefore = priceOf(line);
            line.product = freshItem;

            if (priceOf(line) !== priceBefore) {
                changed.push({ line, priceBefore });
            }

            return true;
        });

        return { changed, removed };
    }

    function remove(line) {
        lines.value = lines.value.filter((candidate) => candidate.key !== line.key);
    }

    function clear() {
        lines.value = [];
    }

    /** The lines as the sale request wants them. */
    const saleItemsPayload = computed(() => lines.value.map((line) => ({ product_unit_id: line.unitId, quantity: quantityOf(line) })));

    /** The part of the cart that is in stock, as the sale request wants it; when nothing is short it is the whole cart. */
    const inStockItemsPayload = computed(() => toPayload(split.value.saleLines));

    /** The part of the cart that is short, as the order request wants it. */
    const shortItemsPayload = computed(() => toPayload(split.value.orderLines));

    return { lines, total, isEmpty, hasOverStock, split, inStockItemsPayload, shortItemsPayload, saleItemsPayload, unitOf, quantityOf, priceOf, subtotalOf, isOverStock, stockWarningOf, add, refresh, remove, clear };
}
