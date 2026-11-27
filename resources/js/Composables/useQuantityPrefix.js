const QUANTITY_PREFIX = /^(\d+(?:[.,]\d+)?)\s*\*\s*/;

/**
 * The cashier's shorthand "12*" in front of what is typed or scanned: the number before the asterisk is how many to add,
 * the rest is what is looked up. Only an asterisk counts (a letter x would swallow codes like "5x20"), and 0 is no quantity.
 * @param {string} term what is in the search box
 * @returns {{quantity: number, lookupTerm: string, isPrefixed: boolean}} quantity is 1 without a prefix
 */
export function splitQuantityPrefix(term) {
    const match = term.match(QUANTITY_PREFIX);
    const quantity = match ? Number(match[1].replace(',', '.')) : 0;

    if (quantity <= 0) {
        return { quantity: 1, lookupTerm: term, isPrefixed: false };
    }

    return { quantity, lookupTerm: term.slice(match[0].length), isPrefixed: true };
}
