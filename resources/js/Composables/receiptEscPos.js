import { formatReceiptDateTime } from './receiptDateTime';
import { formatFigure as figure } from './useMoneyFormat';
import { formatQuantity } from './useQuantityFormat';

/**
 * A receipt as the text the printer itself prints (ESC/POS), for a store that prints through QZ Tray.
 *
 * The page of a receipt is sent to a dot matrix printer as a picture, which the renderer of QZ Tray lays out by its own rules
 * (it centres every line narrower than the paper) and the printer draws with dropped dots. Text is laid out here, one line of
 * as many characters as the printer's own font holds, and so sits where it is put and prints as clean as the printer's font.
 */

/** Characters in a line of the standard font, per roll width in millimetres. Known only for the 76 mm roll of the Epson TM-U220. */
const COLUMNS_BY_WIDTH = { 76: 33 };

const ESC = '\x1B';

/**
 * Resets the printer and picks font A (ESC M n: the characters "0" and "1" choose font A and B without a control byte). Left
 * to its default the TM-U220 printed the narrower font B, so 33 columns filled two thirds of the roll; font A is the font the
 * 33 columns are counted in, and fills the printable width.
 */
const INITIALIZE = `${ESC}@${ESC}M0`;

/**
 * ESC E n turns bold on or off by the lowest bit of n, so the characters "1" and "0" do it without a control byte: a zero byte
 * can be cut off on its way to the printer, and it would take the rest of the receipt with it.
 */
const BOLD_ON = `${ESC}E1`;
const BOLD_OFF = `${ESC}E0`;

/**
 * Blank lines after the last line, to push it out past the tear-off bar of the printer. Six left the last two lines of a
 * receipt behind the bar, where the tear cut through the footer and the rest went on the next receipt; nine clears them.
 */
const BLANK_LINES_AFTER = 9;

/** ESC i cuts the paper; the cutter sits about eight lines past the print head, which is why the blank lines come first. A printer without a cutter ignores it. */
const CUT_PAPER = `${ESC}i`;

/**
 * ESC p m t1 t2 pulses the cash drawer: m "0" is the first drawer pin, t1 and t2 are the on and off times in steps of 2 ms
 * ("2" and "z" are 100 ms and 244 ms). All plain characters, so no zero byte is sent.
 */
const OPEN_DRAWER = `${ESC}p02z`;

/** The accents that NFD splits off a letter (U+0300 to U+036F), built from their codes so the source stays plain ASCII. */
const COMBINING_MARKS = new RegExp(`[${String.fromCharCode(0x300)}-${String.fromCharCode(0x36f)}]`, 'g');

/**
 * The standard font holds plain ASCII; an accent is dropped and any other character becomes a question mark.
 *
 * @param {string} text
 */
function printable(text) {
    return String(text)
        .normalize('NFD')
        .replace(COMBINING_MARKS, '')
        .replace(/[^\x20-\x7E\n]/g, '?');
}

/**
 * Breaks a text into lines of at most `columns` characters at its spaces; a word longer than a line is cut. A line break in
 * the text stays one.
 *
 * @param {string} text
 * @param {number} columns
 * @returns {string[]}
 */
function wrap(text, columns) {
    const lines = [];

    for (const paragraph of printable(text).split('\n')) {
        let line = '';

        for (let word of paragraph.split(/\s+/).filter(Boolean)) {
            while (word.length > columns) {
                if (line !== '') {
                    lines.push(line);
                    line = '';
                }

                lines.push(word.slice(0, columns));
                word = word.slice(columns);
            }

            if (line === '') {
                line = word;
            } else if (line.length + 1 + word.length <= columns) {
                line += ` ${word}`;
            } else {
                lines.push(line);
                line = word;
            }
        }

        lines.push(line);
    }

    return lines;
}

/** The lines of a receipt, each placed at the left, the middle or the right of the printer's columns. */
class ReceiptText {
    /** @param {number} columns */
    constructor(columns) {
        this.columns = columns;
        /** @type {string[]} */
        this.lines = [];
    }

    /**
     * @param {string} text
     * @param {{isBold?: boolean}} [options]
     */
    left(text, { isBold = false } = {}) {
        this.#push(wrap(text, this.columns), isBold);
    }

    /**
     * @param {string} text
     * @param {{isBold?: boolean}} [options]
     */
    center(text, { isBold = false } = {}) {
        this.#push(wrap(text, this.columns).map((line) => ' '.repeat(Math.floor((this.columns - line.length) / 2)) + line), isBold);
    }

    /**
     * @param {string} text
     * @param {{isBold?: boolean}} [options]
     */
    right(text, { isBold = false } = {}) {
        this.#push(wrap(text, this.columns).map((line) => line.padStart(this.columns)), isBold);
    }

    /**
     * A line with one text at the left edge and another at the right; when both do not fit, the second goes on its own line.
     *
     * @param {string} leftText
     * @param {string} rightText
     */
    spread(leftText, rightText) {
        const leftLine = printable(leftText);
        const rightLine = printable(rightText);

        if (leftLine.length + 1 + rightLine.length <= this.columns) {
            this.lines.push(leftLine + ' '.repeat(this.columns - leftLine.length - rightLine.length) + rightLine);

            return;
        }

        this.left(leftLine);
        this.right(rightLine);
    }

    rule() {
        this.lines.push('-'.repeat(this.columns));
    }

    blank() {
        this.lines.push('');
    }

    /**
     * @param {{isDrawerOpened?: boolean}} [options] whether the cash drawer is opened once the receipt is cut
     * @returns {string} the lines, ready to send to the printer
     */
    toString({ isDrawerOpened = false } = {}) {
        return `${INITIALIZE}${this.lines.join('\n')}\n${'\n'.repeat(BLANK_LINES_AFTER)}${CUT_PAPER}${isDrawerOpened ? OPEN_DRAWER : ''}`;
    }

    /**
     * @param {string[]} lines
     * @param {boolean} isBold
     */
    #push(lines, isBold) {
        this.lines.push(...lines.map((line) => (isBold ? `${BOLD_ON}${line}${BOLD_OFF}` : line)));
    }
}

/**
 * @param {ReceiptText} text
 * @param {{name: string, quantity: string|number, unit: string, unit_price: string|number, subtotal: string|number}[]} items
 */
function addItems(text, items) {
    for (const item of items) {
        text.left(item.name);
        text.spread(`${formatQuantity(item.quantity)} ${item.unit} x Rp. ${figure(item.unit_price)}`, figure(item.subtotal));
    }
}

/**
 * @param {ReceiptText} text
 * @param {{method: string, amount: string|number}[]} payments
 * @param {string|number} changeAmount
 */
function addPayments(text, payments, changeAmount) {
    for (const payment of payments) {
        text.right(`${payment.method} : ${figure(payment.amount)}`);
    }

    text.right(`Kembalian : ${figure(changeAmount)}`);
}

/**
 * The receipt page's props as the text to print, with the same lines and the same order as the page.
 *
 * @param {{order: object|null, number: string, sold_at: string, cashier_name: string, customer_name: string|null, status: string, is_copy: boolean, store: {name: string, address: string|null, phone: string|null}, footer: string, paper_width: number, items: object[], payments: object[], total: string, change_amount: string}} receipt
 * @param {{isDrawerOpened?: boolean}} [options] true for the print that closes a sale, which opens the cash drawer; a copy printed again does not
 * @returns {string|null} null when the roll is of a width whose columns are not known, so the page is printed instead
 */
export function receiptEscPosOf(receipt, { isDrawerOpened = false } = {}) {
    const columns = COLUMNS_BY_WIDTH[receipt.paper_width];

    if (columns === undefined) {
        return null;
    }

    const text = new ReceiptText(columns);
    const order = receipt.order;

    text.center(receipt.store.name, { isBold: true });

    if (receipt.store.address) {
        text.center(receipt.store.address);
    }

    if (receipt.store.phone) {
        text.center(`No Tlp. ${receipt.store.phone}`);
    }

    if (receipt.status === 'voided' || receipt.is_copy) {
        text.blank();
        text.center(receipt.status === 'voided' ? 'VOID' : 'SALINAN', { isBold: true });
    }

    text.blank();
    text.left(`No : ${receipt.number}`);
    text.left(`Tanggal : ${formatReceiptDateTime(receipt.sold_at)}`);
    text.left(`Kasir : ${receipt.cashier_name}`);

    if (receipt.customer_name) {
        text.left(`Pelanggan : ${receipt.customer_name}`);
    }

    if (order?.customer_phone) {
        text.left(`Telepon : ${order.customer_phone}`);
    }

    text.blank();
    addItems(text, receipt.items);
    text.rule();
    text.right(`Total : ${figure(receipt.total)}`);

    if (order === null) {
        addPayments(text, receipt.payments, receipt.change_amount);
    } else {
        text.rule();
        text.left(`PESANAN ${order.number}`, { isBold: true });
        text.left(order.status === 'completed' ? 'Sudah diambil' : 'Barang menyusul');
        addItems(text, order.items);
        text.right(`Total pesanan : ${figure(order.total)}`);
        text.right(`Uang muka : ${figure(order.deposit)}`);
        text.right(`Sisa dibayar saat diambil : ${figure(order.remaining)}`, { isBold: true });
        text.left('Bawa nota ini saat mengambil barang. Harga sesuai nota ini.');
        text.rule();
        text.right(`Uang muka pesanan : ${figure(order.deposit)}`);
        text.right(`Total dibayar : ${figure(Number(receipt.total) + Number(order.deposit))}`);
        addPayments(text, receipt.payments, receipt.change_amount);
    }

    text.blank();
    text.center(receipt.footer);
    text.center(receipt.number);

    return text.toString({ isDrawerOpened });
}

/**
 * A made-up receipt as text, for the test button of the printer settings: it prints what a real one would, in the mode the
 * store chose.
 *
 * @param {number} widthMm the width of the roll
 * @returns {string|null} null when the roll is of a width whose columns are not known
 */
export function sampleReceiptEscPos(widthMm) {
    return receiptEscPosOf({
        order: null,
        number: 'INV/TES/0001',
        sold_at: new Date().toISOString(),
        cashier_name: 'Tes Cetak',
        customer_name: null,
        status: 'completed',
        is_copy: false,
        store: { name: 'Toko Contoh', address: 'Jl. Contoh No. 1', phone: '0812-3456-7890' },
        footer: 'Terimakasih Telah Berbelanja',
        paper_width: widthMm,
        items: [{ name: 'Paku 7cm Gypq', quantity: '0.5', unit: 'KG', unit_price: '24000', subtotal: '12000' }],
        payments: [{ method: 'Tunai', amount: '20000' }],
        total: '12000',
        change_amount: '8000',
    });
}
