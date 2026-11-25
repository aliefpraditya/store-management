/**
 * The chip colour for each status of a purchase (nota) on the list and the detail screen.
 * @type {Record<string, string>}
 */
export const purchaseStatusChipClass = {
    draft: 'chip--off',
    ordered: 'chip--warn',
    partially_received: 'chip--warn',
    received: 'chip--ok',
    closed: 'chip--off',
    cancelled: 'chip--bad',
};
