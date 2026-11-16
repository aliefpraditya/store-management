/**
 * The conversion of a unit is how many base units one of it holds, stored with 3 decimals. The calculator lets people
 * say it the way they think of it, from either side: "1 DUS = 10 KALENG", or "1 KALENG = 0.1 DUS" the other way round.
 */

export const CONVERSION_DECIMALS = 3;

const roundToStored = (value) => Math.round((value + Number.EPSILON) * 10 ** CONVERSION_DECIMALS) / 10 ** CONVERSION_DECIMALS;

/**
 * @typedef {'unit-holds-base' | 'base-holds-unit'} ConversionDirection
 *   `unit-holds-base`: 1 of this unit = n base units (conversion n).
 *   `base-holds-unit`: 1 base unit = n of this unit (conversion 1 / n).
 */

/**
 * @param {string|number} count the n typed in the calculator
 * @param {ConversionDirection} direction
 * @returns {{conversion: number, isExact: boolean}|null} the conversion to store, and whether storing it loses nothing
 *   (1 DUS = 12 KALENG seen from the DUS side is 0.0833..., which 3 decimals cannot hold); null when count is not above 0
 *   or the conversion would round down to 0
 */
export function conversionFromCount(count, direction) {
    const amount = Number(count);

    if (count === '' || count === null || !Number.isFinite(amount) || amount <= 0) {
        return null;
    }

    const exactConversion = direction === 'unit-holds-base' ? amount : 1 / amount;
    const conversion = roundToStored(exactConversion);

    if (conversion <= 0) {
        return null;
    }

    return { conversion, isExact: Math.abs(conversion - exactConversion) < 1e-9 };
}
