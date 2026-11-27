/**
 * The lines of the daily report for a roll, shared by the page of the report and its text for the printer, so the two cannot
 * drift apart. A row's `value` is left unformatted for each to format its own way; a `heading` row has none and a `note` row
 * is a line of text.
 *
 * @typedef {{kind: 'money'|'deduction'|'count'|'heading'|'note', label: string, value?: string|number}} DailyReportRow
 * @typedef {{title: string, rows: DailyReportRow[]}} DailyReportSection
 */

/** @param {string} isoDay e.g. `2026-11-02` @returns {string} e.g. `02/11/2026` */
export const dayText = (isoDay) => isoDay.split('-').reverse().join('/');

const money = (label, value) => ({ kind: 'money', label, value });
const deduction = (label, value) => ({ kind: 'deduction', label, value });

/**
 * @param {Record<string, string|number>} figures
 * @returns {DailyReportSection}
 */
function salesSection(figures) {
    return {
        title: 'Penjualan',
        rows: [
            { kind: 'count', label: 'Jumlah nota', value: figures.sales_count },
            money('Tunai', figures.sales_cash),
            money('Transfer', figures.sales_transfer),
            money('QRIS', figures.sales_qris),
            money('EDC', figures.sales_edc),
            money('COD ditagih', figures.sales_cod),
            money('Total penjualan', figures.total_sales),
        ],
    };
}

/**
 * The COD money received on the day, none when nothing was received.
 *
 * @param {Record<string, string|number>} figures
 * @returns {DailyReportSection[]}
 */
function codReceivedSections(figures) {
    const received = [figures.cod_received_cash, figures.cod_received_transfer, figures.cod_received_qris, figures.cod_received_edc];

    if (!received.some((amount) => Number(amount) > 0)) {
        return [];
    }

    return [
        {
            title: 'Pelunasan COD diterima',
            rows: [money('Tunai (masuk laci)', figures.cod_received_cash), money('Transfer', figures.cod_received_transfer), money('QRIS', figures.cod_received_qris), money('EDC', figures.cod_received_edc)],
        },
    ];
}

/**
 * @param {Record<string, string|number>} figures
 * @returns {DailyReportSection}
 */
function returnsAndExpensesSection(figures) {
    return {
        title: 'Retur dan pengeluaran',
        rows: [
            money('Retur disetujui', figures.total_returns),
            deduction('Pengembalian tunai', figures.refunds_cash),
            deduction('Pengeluaran dari laci', figures.expenses_drawer),
            money('Pengeluaran sumber lain', figures.expenses_other),
            money(`Nota void (${figures.voids_count})`, figures.total_voids),
        ],
    };
}

/**
 * @param {{label: string, cashier_name: string, expected_cash: string|null, counted_cash: string|null, cash_difference: string|null, deposit_amount: string|null}[]} shifts
 * @param {Record<string, string|number>} figures
 * @returns {DailyReportSection}
 */
function shiftsSection(shifts, figures) {
    if (shifts.length === 0) {
        return { title: 'Kas per shift', rows: [{ kind: 'note', label: 'Tidak ada shift pada hari ini.' }] };
    }

    const rows = shifts.flatMap((shift) => [
        { kind: 'heading', label: `${shift.label} - ${shift.cashier_name}` },
        money('Seharusnya', shift.expected_cash ?? 0),
        money('Dihitung', shift.counted_cash ?? 0),
        money('Selisih', shift.cash_difference ?? 0),
        money('Setoran', shift.deposit_amount ?? 0),
    ]);

    return {
        title: 'Kas per shift',
        rows: [
            ...rows,
            { kind: 'heading', label: `Total (${figures.shifts_count} shift)` },
            money('Seharusnya', figures.total_expected_cash),
            money('Dihitung', figures.total_counted_cash),
            money('Selisih', figures.total_cash_difference),
            money('Setoran', figures.total_deposit),
        ],
    };
}

/**
 * The receipts voided on the day, none when there were none.
 *
 * @param {{number: string, sold_on: string, reason: string|null, total: string}[]} voids
 * @returns {DailyReportSection[]}
 */
function voidsSections(voids) {
    if (voids.length === 0) {
        return [];
    }

    return [
        {
            title: 'Nota void pada hari ini',
            rows: voids.flatMap((sale) => [
                money(sale.number, sale.total),
                { kind: 'note', label: `dijual ${dayText(sale.sold_on)}${sale.reason ? `, ${sale.reason}` : ''}` },
            ]),
        },
    ];
}

/**
 * @param {{figures: Record<string, string|number>, shifts: object[], voids: object[]}} report
 * @returns {DailyReportSection[]}
 */
export function dailyReportSectionsOf(report) {
    return [
        salesSection(report.figures),
        ...codReceivedSections(report.figures),
        returnsAndExpensesSection(report.figures),
        shiftsSection(report.shifts, report.figures),
        ...voidsSections(report.voids),
    ];
}
