import { TextLines } from './escPosText';
import { formatReceiptDateTime } from './receiptDateTime';
import { BOLD_OFF, BOLD_ON, COLUMNS_BY_WIDTH, printerTextOf } from './receiptEscPos';
import { codReceivedRowsOf, codRowsOf } from './shiftReportRows';
import { formatFigure as figure } from './useMoneyFormat';

/**
 * An end-of-shift report as the text the printer itself prints (ESC/POS), for a store that prints through QZ Tray. It has the
 * same lines in the same order as the page ({@see ShiftReportSheet}), laid out in the printer's own columns for the reason given
 * in {@see receiptEscPosOf}. Amounts are plain figures: the report says rupiah once, as a receipt does.
 */

/**
 * @param {TextLines} text
 * @param {string} title
 */
function addSection(text, title) {
    text.blank();
    text.left(title, { isBold: true });
}

/**
 * @param {TextLines} text
 * @param {import('./shiftReportRows').ShiftReportRow[]} rows
 */
function addRows(text, rows) {
    for (const row of rows) {
        text.spread(row.label.replace('·', '-'), row.isMoney ? figure(row.value) : String(row.value));
    }
}

/**
 * @param {TextLines} text
 * @param {string} label
 * @param {string|number} amount
 * @param {{isDeduction?: boolean}} [options] a deduction is printed with its minus sign
 */
function addMoney(text, label, amount, { isDeduction = false } = {}) {
    text.spread(label, figure(isDeduction ? -Math.abs(Number(amount)) : amount));
}

/**
 * @param {TextLines} text
 * @param {object} report
 */
function addFullSales(text, report) {
    addSection(text, 'Penjualan');
    text.spread('Jumlah nota', String(report.sale_count));
    addMoney(text, 'Total penjualan', report.total_sales);

    for (const payment of report.payments) {
        addMoney(text, payment.method, payment.amount);
    }

    if (Number(report.void_deposits) > 0) {
        addMoney(text, 'Termasuk DP nota void', report.void_deposits);
    }

    if (Number(report.returns_total) > 0) {
        addMoney(text, 'Retur disetujui', report.returns_total);
    }
}

/**
 * @param {TextLines} text
 * @param {object} report
 */
function addFullCash(text, report) {
    addSection(text, 'Kas');
    addMoney(text, 'Modal kas awal', report.opening_cash);

    if (Number(report.order_deposit_cash) !== 0) {
        addMoney(text, 'DP pesanan tunai', report.order_deposit_cash);
    }

    if (Number(report.cod_received?.cash) > 0) {
        addMoney(text, 'Pelunasan COD tunai', report.cod_received.cash);
    }

    if (Number(report.cash_refunds) > 0) {
        addMoney(text, 'Retur tunai', report.cash_refunds, { isDeduction: true });
    }

    if (Number(report.void_cash_refunds) > 0) {
        addMoney(text, 'DP void dikembalikan tunai', report.void_cash_refunds, { isDeduction: true });
    }

    if (Number(report.drawer_expenses) > 0) {
        addMoney(text, 'Pengeluaran dari laci', report.drawer_expenses, { isDeduction: true });
    }

    addMoney(text, 'Tunai seharusnya', report.expected_cash);
}

/**
 * What the cashier counted and left, which the blind report holds as well as the full one.
 *
 * @param {TextLines} text
 * @param {object} report
 */
function addCountedCash(text, report) {
    if (report.is_blind) {
        addSection(text, 'Kas');
        addMoney(text, 'Modal kas awal', report.opening_cash);
    } else {
        text.blank();
    }

    const orDash = (amount) => (amount === null ? '-' : figure(amount));

    text.spread('Tunai dihitung', orDash(report.counted_cash));

    if (!report.is_blind && report.counted_cash !== null) {
        addMoney(text, 'Selisih', report.cash_difference);
    }

    text.spread('Ditinggal di laci', orDash(report.drawer_left_cash));
    text.spread('Setoran', orDash(report.deposit_amount));
}

/**
 * @param {TextLines} text
 * @param {object} report
 */
function addNotes(text, report) {
    if (report.opening_cash_note && !report.is_blind) {
        text.blank();
        text.left(`Alasan modal awal berbeda: ${report.opening_cash_note}`);
    }

    if (report.closing_note) {
        text.blank();
        text.left(`Catatan kasir: ${report.closing_note}`);
    }

    if (report.is_blind && report.status === 'pending_review') {
        text.blank();
        text.left('Hitungan menunggu review Admin. Serahkan setoran sesuai angka di atas.');
    } else if (!report.is_blind && report.difference_status !== 'none') {
        const decidedBy = report.decided_by_name ? ` oleh ${report.decided_by_name}` : '';

        text.blank();
        text.left(`Selisih: ${report.difference_status_label}${decidedBy}. ${report.approval_note ?? ''}`.trim());
    }
}

/**
 * The shift report page's props as the text to print, with the same lines and the same order as the page.
 *
 * @param {{is_blind: boolean, label: string, register_name: string, cashier_name: string, business_date: string, opened_at: string, counted_at: string|null, closed_at: string|null, status_label: string, store: {name: string, address: string|null, phone: string|null}, paper_width: number}} report
 * @returns {string|null} null when the roll is of a width whose columns are not known, so the page is printed instead
 */
export function shiftReportEscPosOf(report) {
    const columns = COLUMNS_BY_WIDTH[report.paper_width];

    if (columns === undefined) {
        return null;
    }

    const text = new TextLines(columns, { on: BOLD_ON, off: BOLD_OFF });

    text.center(report.store.name, { isBold: true });

    if (report.store.address) {
        text.center(report.store.address);
    }

    if (report.store.phone) {
        text.center(`No Tlp. ${report.store.phone}`);
    }

    text.blank();
    text.center('LAPORAN AKHIR SHIFT', { isBold: true });
    text.blank();
    text.left(`${report.label} - ${report.register_name}`);
    text.left(`Kasir : ${report.cashier_name}`);
    text.left(`Tanggal : ${report.business_date.split('-').reverse().join('/')}`);
    text.left(`Buka : ${formatReceiptDateTime(report.opened_at)}`);

    if (report.counted_at) {
        text.left(`Dihitung : ${formatReceiptDateTime(report.counted_at)}`);
    }

    text.left(report.closed_at ? `Tutup : ${formatReceiptDateTime(report.closed_at)}` : `Status : ${report.status_label}`);

    if (report.is_blind) {
        text.blank();
        text.spread('Jumlah nota', String(report.sale_count));
    } else {
        addFullSales(text, report);
    }

    const codRows = [...codRowsOf(report), ...codReceivedRowsOf(report)];

    if (codRows.length > 0) {
        text.blank();
        addRows(text, codRows);
    }

    if (!report.is_blind) {
        addFullCash(text, report);
    }

    addCountedCash(text, report);

    if (!report.is_blind && report.expenses.length > 0) {
        addSection(text, 'Pengeluaran dari laci');

        for (const expense of report.expenses) {
            text.spread(expense.status === 'voided' ? `${expense.description} (batal)` : expense.description, figure(expense.amount));
        }
    }

    addNotes(text, report);

    text.blank();
    text.blank();
    text.blank();
    text.spread('Kasir', 'Penerima setoran');

    return printerTextOf(text.lines, false);
}
