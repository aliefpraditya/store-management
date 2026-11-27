const SEN_PER_RUPIAH = 100;
const MILLI_PER_UNIT = 1000;

/**
 * What a receipt line costs: quantity times price to the sen, and, when the quantity is not a whole number, rounded up
 * to a multiple of the store's selling price step (no step, no rounding). A whole quantity keeps the price the item form
 * says. The server works the same out in `CartPricer`; keep the two in step.
 *
 * Money is worked out in whole sen so that 0.37 kg of Rp 24.000 does not drift.
 *
 * @param {number|string} quantity up to 3 decimals
 * @param {number|string} unitPrice rupiah, up to 2 decimals
 * @param {number} sellingPriceStep rupiah, 0 for no rounding
 * @returns {number} rupiah
 */
export function lineSubtotal(quantity, unitPrice, sellingPriceStep) {
    const milliQuantity = Math.round((Number(quantity) || 0) * MILLI_PER_UNIT);
    const priceInSen = Math.round((Number(unitPrice) || 0) * SEN_PER_RUPIAH);
    const subtotalInSen = Math.round((milliQuantity * priceInSen) / MILLI_PER_UNIT);
    const isWholeQuantity = milliQuantity % MILLI_PER_UNIT === 0;
    const stepInSen = Math.round((Number(sellingPriceStep) || 0) * SEN_PER_RUPIAH);

    if (stepInSen <= 0 || isWholeQuantity) {
        return subtotalInSen / SEN_PER_RUPIAH;
    }

    return (Math.ceil(subtotalInSen / stepInSen) * stepInSen) / SEN_PER_RUPIAH;
}

/**
 * What returning `quantity` of a receipt line gives back. Normally quantity times price. A line whose subtotal was
 * rounded up (a decimal quantity under a selling price step) was paid for at that subtotal, so a return gives back its
 * share of it, and returning the last of the line gives back what is left. `SaleReturnService::refundOf` does the same.
 *
 * @param {{quantity: string, remaining: string, unit_price: string, subtotal: string, refunded: string}} line a receipt line as the return window gets it
 * @param {number|string} quantity how much of it comes back
 * @returns {number} rupiah
 */
export function refundOfLine(line, quantity) {
    const returned = Number(quantity) || 0;
    const unitPrice = Number(line.unit_price);
    const paid = Number(line.subtotal);
    const priceOfQuantity = Math.round(returned * unitPrice * SEN_PER_RUPIAH) / SEN_PER_RUPIAH;
    const priceOfWholeLine = Math.round(Number(line.quantity) * unitPrice * SEN_PER_RUPIAH) / SEN_PER_RUPIAH;

    if (paid === priceOfWholeLine) {
        return priceOfQuantity;
    }

    if (returned === Number(line.remaining)) {
        return Math.round((paid - Number(line.refunded)) * SEN_PER_RUPIAH) / SEN_PER_RUPIAH;
    }

    return Math.round(((paid * returned) / Number(line.quantity)) * SEN_PER_RUPIAH) / SEN_PER_RUPIAH;
}
