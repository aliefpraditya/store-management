/**
 * The chip colour for each status of a purchase (nota) on the list and the detail screen.
 * @type {Record<string, string>}
 */
export const purchaseStatusChipClass = {
    draft: 'chip--off',
    ordered: 'chip--warn',
    shipped: 'chip--warn',
    completed: 'chip--ok',
    cancelled: 'chip--bad',
};
