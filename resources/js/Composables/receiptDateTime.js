const dateFormat = new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: '2-digit', year: 'numeric' });
const timeFormat = new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit' });

/**
 * The date and time of a receipt as it is printed.
 *
 * @param {string} soldAt an ISO date and time
 * @returns {string} e.g. "26/11/2026 12.36"
 */
export function formatReceiptDateTime(soldAt) {
    const soldAtDate = new Date(soldAt);

    return `${dateFormat.format(soldAtDate)} ${timeFormat.format(soldAtDate)}`;
}
