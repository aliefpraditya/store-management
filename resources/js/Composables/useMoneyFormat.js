const rupiahFormat = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });

/**
 * An amount as plain figures, for paper that prints the currency on its own (a roll receipt).
 *
 * @param {string|number} amount
 * @returns {string} e.g. "1.280.000"
 */
export function formatFigure(amount) {
    return rupiahFormat.format(Number(amount));
}

/**
 * @param {string|number} amount
 * @returns {string} e.g. "Rp 130.000"; a negative amount keeps its minus sign in front of the Rp
 */
export function formatRupiah(amount) {
    const value = Number(amount);

    return `${value < 0 ? '−' : ''}Rp ${rupiahFormat.format(Math.abs(value))}`;
}

/**
 * An amount that is taken off something, shown with its minus sign so a report reader sees it lowers the total.
 *
 * @param {string|number} amount the positive amount, e.g. "150000.00"
 * @returns {string} e.g. "−Rp 150.000"
 */
export function formatDeduction(amount) {
    return formatRupiah(-Math.abs(Number(amount)));
}
