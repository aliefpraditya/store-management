/**
 * Margin is a share of the selling price (never of the cost): margin% = (price − cost) / price × 100, so
 * price = cost / (1 − margin%). Money is rounded to the sen it is stored with, margin to 2 decimals.
 */

const roundTo = (value, decimals) => {
    const factor = 10 ** decimals;

    return Math.round((value + Number.EPSILON) * factor) / factor;
};

/**
 * @param {string|number} cost purchase price of the unit
 * @param {string|number} marginPercent
 * @returns {number|null} the selling price, or null when the margin is not a number or is 100 or more (no price gives it)
 */
export function priceFromMargin(cost, marginPercent) {
    const margin = Number(marginPercent);

    if (marginPercent === '' || marginPercent === null || !Number.isFinite(margin) || margin >= 100) {
        return null;
    }

    return roundTo((Number(cost || 0) * 100) / (100 - margin), 2);
}

/**
 * @param {string|number} cost purchase price of the unit
 * @param {string|number} price selling price of the unit
 * @returns {number|null} the margin in percent, or null when there is no price to take it from
 */
export function marginFromPrice(cost, price) {
    const sellingPrice = Number(price);

    if (price === '' || price === null || !Number.isFinite(sellingPrice) || sellingPrice <= 0) {
        return null;
    }

    return roundTo(((sellingPrice - Number(cost || 0)) / sellingPrice) * 100, 2);
}
