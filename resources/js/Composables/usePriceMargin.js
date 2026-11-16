/**
 * Margin is a share of the selling price (never of the cost): margin% = (price - cost) / price x 100, so
 * price = cost / (1 - margin%). Money is worked out in whole sen with BigInt and rounded half up to the sen it is
 * stored with, the way the server does; in floating point 1.15 x 1.5 can land a sen away from the server's answer.
 */

const MONEY_DECIMALS = 2;
const MARGIN_DECIMALS = 2;
const CONVERSION_DECIMALS = 3;

/**
 * @param {string|number|null} value
 * @param {number} decimals
 * @returns {bigint|null} the value times 10^decimals, rounded half up (away from zero); null when it is not a number
 */
function toScaled(value, decimals) {
    const text = String(value ?? '').trim();
    const match = /^(-?)(\d*)(?:\.(\d*))?$/.exec(text);

    if (match === null || (match[2] === '' && !match[3])) {
        const number = Number(text);

        return text !== '' && Number.isFinite(number) ? BigInt(Math.round(number * 10 ** decimals)) : null;
    }

    const [, sign, whole, fraction = ''] = match;
    const digits = fraction.padEnd(decimals + 1, '0');
    const scaled = BigInt((whole || '0') + digits.slice(0, decimals)) + (digits[decimals] >= '5' ? 1n : 0n);

    return sign === '-' ? -scaled : scaled;
}

/**
 * @param {bigint} numerator
 * @param {bigint} denominator above 0
 * @returns {bigint} the quotient rounded half up (away from zero)
 */
function divideRounded(numerator, denominator) {
    const magnitude = numerator < 0n ? -numerator : numerator;
    const quotient = (2n * magnitude + denominator) / (2n * denominator);

    return numerator < 0n ? -quotient : quotient;
}

/**
 * @param {string|number} cost purchase price of the unit
 * @param {string|number} marginPercent
 * @returns {number|null} the selling price, or null when the margin is not a number or is 100 or more (no price gives it)
 */
export function priceFromMargin(cost, marginPercent) {
    const marginHundredths = toScaled(marginPercent, MARGIN_DECIMALS);
    const costInSen = toScaled(cost || 0, MONEY_DECIMALS);

    if (marginHundredths === null || costInSen === null || marginHundredths >= 10000n) {
        return null;
    }

    return Number(divideRounded(costInSen * 10000n, 10000n - marginHundredths)) / 100;
}

/**
 * @param {string|number} cost purchase price of the unit
 * @param {string|number} price selling price of the unit
 * @returns {number|null} the margin in percent, or null when there is no price to take it from or no cost to measure
 *   it against (a free item would read as 100 percent)
 */
export function marginFromPrice(cost, price) {
    const priceInSen = toScaled(price, MONEY_DECIMALS);
    const costInSen = toScaled(cost || 0, MONEY_DECIMALS);

    if (priceInSen === null || priceInSen <= 0n || costInSen === null || costInSen <= 0n) {
        return null;
    }

    return Number(divideRounded((priceInSen - costInSen) * 10000n, priceInSen)) / 100;
}

/**
 * What a unit costs when it simply follows the base unit: the base cost times the conversion, to the sen.
 *
 * @param {string|number} baseCost purchase price of the base unit
 * @param {string|number} conversionFactor how many base units one of this unit holds
 * @returns {number} 0 when either is not a number
 */
export function costFromBase(baseCost, conversionFactor) {
    const costInSen = toScaled(baseCost || 0, MONEY_DECIMALS);
    const conversionInThousandths = toScaled(conversionFactor || 0, CONVERSION_DECIMALS);

    if (costInSen === null || conversionInThousandths === null) {
        return 0;
    }

    return Number(divideRounded(costInSen * conversionInThousandths, 10n ** BigInt(CONVERSION_DECIMALS))) / 100;
}
