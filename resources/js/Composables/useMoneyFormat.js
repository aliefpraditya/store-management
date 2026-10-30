const rupiahFormat = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });

/**
 * @param {string|number} amount
 * @returns {string} e.g. "Rp 130.000"; a negative amount keeps its minus sign in front of the Rp
 */
export function formatRupiah(amount) {
    const value = Number(amount);

    return `${value < 0 ? '−' : ''}Rp ${rupiahFormat.format(Math.abs(value))}`;
}
