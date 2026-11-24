/**
 * An address of a report page opened on a period: the path and query to hand to a link, so moving to another tab or to a day's
 * receipts does not ask for the period again.
 *
 * @param {string} href The address of the page, without a period.
 * @param {string} from The first day, `Y-m-d`.
 * @param {string} to The last day, `Y-m-d`.
 * @param {Record<string, string>} [extra] More query parameters, e.g. which grouping the page opens on.
 * @returns {string} e.g. `/t/toko/reports/sales/receipts?from=2026-11-01&to=2026-11-30`
 */
export function urlForRange(href, from, to, extra = {}) {
    const url = new URL(href, window.location.origin);

    url.searchParams.set('from', from);
    url.searchParams.set('to', to);

    for (const [key, value] of Object.entries(extra)) {
        url.searchParams.set(key, value);
    }

    return `${url.pathname}${url.search}`;
}
