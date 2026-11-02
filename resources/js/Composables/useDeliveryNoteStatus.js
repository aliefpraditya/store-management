/**
 * The chip colour for each status of a delivery note, shared by the till's dialog and the admin delivery note pages.
 * @type {Record<string, string>}
 */
export const deliveryNoteStatusChipClass = {
    draft: 'chip--off',
    shipped: 'chip--warn',
    delivered: 'chip--ok',
    partially_delivered: 'chip--warn',
    returned: 'chip--bad',
    cancelled: 'chip--bad',
};
