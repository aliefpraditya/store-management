import { dailyReportSectionsOf, dayText } from './dailyReportRows';
import { TextLines } from './escPosText';
import { formatReceiptDateTime } from './receiptDateTime';
import { BOLD_OFF, BOLD_ON, COLUMNS_BY_WIDTH, printerTextOf } from './receiptEscPos';
import { formatFigure as figure } from './useMoneyFormat';

/**
 * The roll form of the daily report as the text the printer itself prints (ESC/POS), for a store that prints through QZ Tray.
 * It has the same sections in the same order as the page ({@see DailyReportRollSheet}), laid out in the printer's own columns
 * for the reason given in {@see receiptEscPosOf}. Amounts are plain figures, and a deduction keeps its minus sign.
 */

/**
 * @param {TextLines} text
 * @param {import('./dailyReportRows').DailyReportRow} row
 */
function addRow(text, row) {
    switch (row.kind) {
        case 'heading':
            text.left(row.label, { isBold: true });
            break;
        case 'note':
            text.left(row.label);
            break;
        case 'deduction':
            text.spread(row.label, figure(-Math.abs(Number(row.value))));
            break;
        case 'count':
            text.spread(row.label, String(row.value));
            break;
        default:
            text.spread(row.label, figure(row.value));
    }
}

/**
 * The daily report page's props as the text to print, with the same lines and the same order as the page.
 *
 * @param {{business_date: string, closed_by_name: string|null, closed_at: string, notes: string|null, figures: Record<string, string|number>, store: {name: string, address: string|null, phone: string|null}, shifts: object[], voids: object[], paper_width: number}} report
 * @returns {string|null} null when the roll is of a width whose columns are not known, so the page is printed instead
 */
export function dailyReportEscPosOf(report) {
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
    text.center('LAPORAN HARIAN', { isBold: true });
    text.center(dayText(report.business_date));

    for (const section of dailyReportSectionsOf(report)) {
        text.blank();
        text.left(section.title, { isBold: true });
        section.rows.forEach((row) => addRow(text, row));
    }

    if (report.notes) {
        text.blank();
        text.left(`Catatan penutupan: ${report.notes}`);
    }

    text.blank();
    text.left(`Ditutup oleh ${report.closed_by_name ?? '-'}`);
    text.left(formatReceiptDateTime(report.closed_at));

    text.blank();
    text.blank();
    text.blank();
    text.spread('Admin', 'Pemilik');

    return printerTextOf(text.lines, false);
}
