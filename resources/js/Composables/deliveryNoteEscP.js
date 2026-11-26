import { printable, wrap } from './escPosText';
import { formatQuantity } from './useQuantityFormat';

/**
 * A delivery note as the text the form printer itself prints (ESC/P, an Epson LX-310 on 9.5 x 5.5 inch continuous form), for a
 * store that prints through QZ Tray.
 *
 * Like the receipt, it is laid out here in the printer's own columns instead of being sent as the picture of a page, which
 * came out twice as wide as the form. The note is the same as the page of `DeliveryNoteSheet`: the store and the recipient at
 * the left, the delivery address at the right, the lines of goods, the notes, and the four signatures.
 */

const ESC = '\x1B';
const FORM_FEED = '\x0C';

/** Characters across the 8 inches the printer prints at 10 characters per inch, the most it prints on a sheet this wide. */
const COLUMNS = 80;

/**
 * A form is 5.5 inches, 33 lines at 6 per inch. The printer is told that length so a form feed lands on the next form; the note
 * itself keeps to fewer lines so nothing runs into the perforation.
 */
const FORM_LINES = 33;
const PRINTED_LINES = 30;

/**
 * Resets the printer and tells it the form length (ESC C n counts lines, and "!" is 33), picks 10 characters per inch (ESC P)
 * and the draft print (ESC x n: "0" is draft, which strikes hardest through the copies of a multi-part form). The parameters
 * are plain characters, so no zero byte is sent.
 */
const INITIALIZE = `${ESC}@${ESC}C${String.fromCharCode(FORM_LINES)}${ESC}P${ESC}x0`;

const BOLD_ON = `${ESC}E`;
const BOLD_OFF = `${ESC}F`;

const HEADER_LEFT_WIDTH = 40;
const HEADER_GAP = 2;
const HEADER_RIGHT_WIDTH = COLUMNS - HEADER_LEFT_WIDTH - HEADER_GAP;

const NAME_WIDTH = 44;
const CHECK_WIDTH = 6;
const QUANTITY_WIDTH = 10;
const UNIT_WIDTH = 14;
const COLUMN_GAP = '  ';

const SIGNERS = ['Pembuat', 'Penyiap', 'Pengantar', 'Penerima'];

const dateTimeFormat = new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' });

const RULE = '-'.repeat(COLUMNS);

/** The letterhead reads "TOKO ..."; a store whose name already starts that way is not given the word twice. */
function storeTitleOf(storeName) {
    return /^toko\b/i.test(storeName) ? storeName : `TOKO ${storeName}`;
}

/**
 * @param {string} text
 * @param {number} width
 */
function centered(text, width) {
    return ' '.repeat(Math.floor((width - text.length) / 2)) + text;
}

/**
 * Texts broken into lines of a column's width, each remembering whether it is bold.
 *
 * @param {{text: string, isBold?: boolean}[]} cells
 * @param {number} width
 * @returns {{text: string, isBold: boolean}[]}
 */
function columnLines(cells, width) {
    return cells.flatMap(({ text, isBold = false }) => wrap(text, width).map((line) => ({ text: line, isBold })));
}

/**
 * @param {{text: string, isBold: boolean}|undefined} line
 * @param {number} width the line is padded to this when it is not the last of its row
 * @param {boolean} isPadded
 */
function cellText(line, width, isPadded) {
    const text = line?.text ?? '';
    const shown = line?.isBold ? `${BOLD_ON}${text}${BOLD_OFF}` : text;

    return isPadded ? shown + ' '.repeat(width - text.length) : shown;
}

/**
 * The store and the recipient at the left, the delivery address at the right, side by side.
 *
 * @param {object} note
 * @returns {string[]}
 */
function headerLinesOf(note) {
    const left = columnLines(
        [
            { text: storeTitleOf(note.store.name), isBold: true },
            ...(note.store.phone ? [{ text: `Tlp: ${note.store.phone}` }] : []),
            { text: `No DO: ${note.number}` },
            { text: `Tanggal : ${dateTimeFormat.format(new Date(note.created_at))}` },
            { text: `Nama Penerima: ${note.recipient_name}` },
            ...(note.recipient_phone ? [{ text: `No tlp. : ${note.recipient_phone}` }] : []),
        ],
        HEADER_LEFT_WIDTH,
    );

    const mark = note.status === 'cancelled' ? 'BATAL' : note.is_copy ? 'SALINAN' : null;
    const carrier = [note.driver_name, note.vehicle_number].filter(Boolean).join(' / ');
    const saleNumbers = [note.sale.number, ...(note.other_sale_numbers ?? [])].join(', ');
    const right = columnLines(
        [
            ...(mark === null ? [] : [{ text: mark, isBold: true }]),
            { text: 'Alamat Pengantaran:', isBold: true },
            ...(note.recipient_address ? [{ text: note.recipient_address }] : []),
            { text: `No Nota : ${saleNumbers}` },
            ...(carrier === '' ? [] : [{ text: `Pengantar : ${carrier}` }]),
        ],
        HEADER_RIGHT_WIDTH,
    );

    return Array.from({ length: Math.max(left.length, right.length) }, (_, index) => `${cellText(left[index], HEADER_LEFT_WIDTH, true)}${' '.repeat(HEADER_GAP)}${cellText(right[index], HEADER_RIGHT_WIDTH, false)}`.trimEnd());
}

/**
 * @param {string} name
 * @param {string} check
 * @param {string} quantity
 * @param {string} unit
 */
function tableRow(name, check, quantity, unit) {
    return `${name.padEnd(NAME_WIDTH)}${COLUMN_GAP}${centered(check, CHECK_WIDTH).padEnd(CHECK_WIDTH)}${COLUMN_GAP}${quantity.padStart(QUANTITY_WIDTH)}${COLUMN_GAP}${printable(unit).slice(0, UNIT_WIDTH)}`.trimEnd();
}

/**
 * The line of goods; a long name goes on over the lines below it, and the tick box, quantity and unit stay on the first.
 *
 * @param {{name: string, quantity: string|number, unit: string}} item
 * @returns {string[]}
 */
function itemLinesOf(item) {
    return wrap(item.name, NAME_WIDTH).map((nameLine, index) => (index === 0 ? tableRow(nameLine, '[ ]', formatQuantity(item.quantity), item.unit) : tableRow(nameLine, '', '', '')));
}

/** The notes of the note, then the four signatures with room to sign above "Paraf". */
function trailerLinesOf(note) {
    const signatureWidth = COLUMNS / SIGNERS.length;

    return [
        ...(note.notes ? wrap(`Catatan: ${note.notes}`, COLUMNS) : []),
        '',
        SIGNERS.map((signer) => centered(signer, signatureWidth).padEnd(signatureWidth)).join('').trimEnd(),
        '',
        '',
        SIGNERS.map(() => centered('Paraf', signatureWidth).padEnd(signatureWidth)).join('').trimEnd(),
    ];
}

/**
 * The delivery note's props as the text to print: one form when it fits, else more with the heading repeated; the signatures
 * come on the last form.
 *
 * @param {{number: string, created_at: string, status: string, is_copy: boolean, recipient_name: string, recipient_phone: string|null, recipient_address: string|null, driver_name: string|null, vehicle_number: string|null, notes: string|null, sale: {number: string}, other_sale_numbers?: string[], store: {name: string, phone: string|null}, items: object[]}} note
 * @returns {string}
 */
export function deliveryNoteEscPOf(note) {
    const top = [...headerLinesOf(note), RULE, tableRow('Nama Barang', 'Cek', 'Jumlah', 'Satuan'), RULE];
    const trailer = trailerLinesOf(note);
    /** @type {string[][]} */
    const forms = [];
    let form = [...top];

    for (const itemLines of note.items.map(itemLinesOf)) {
        if (form.length + itemLines.length + 1 > PRINTED_LINES) {
            forms.push(form);
            form = [...top];
        }

        form.push(...itemLines);
    }

    form.push(RULE);

    if (form.length + trailer.length > PRINTED_LINES) {
        forms.push(form);
        form = [...top, RULE];
    }

    forms.push([...form, ...trailer]);

    return `${INITIALIZE}${forms.map((lines) => lines.join('\n')).join(FORM_FEED)}${FORM_FEED}`;
}

/**
 * A made-up delivery note as text, for the test button of the form printer: it prints what a real one would.
 *
 * @returns {string}
 */
export function sampleDeliveryNoteEscP() {
    return deliveryNoteEscPOf({
        number: 'SJ/TES/0001',
        created_at: new Date().toISOString(),
        status: 'open',
        is_copy: false,
        recipient_name: 'Pak Contoh',
        recipient_phone: '0812-3456-7890',
        recipient_address: 'Jl. Contoh No. 1\nKel. Contoh, Kec. Contoh',
        driver_name: 'Budi',
        vehicle_number: 'DD 1234 AB',
        notes: 'Barang ditaruh di depan toko.',
        sale: { number: 'INV/TES/0001' },
        other_sale_numbers: [],
        store: { name: 'Toko Contoh', phone: '0812-3456-7890' },
        items: [
            { name: 'Paku 7cm Gypq', quantity: '2', unit: 'KG' },
            { name: 'Seng Talang Maroon 45cm Besar Sekali Panjang', quantity: '10', unit: 'MTR' },
            { name: 'Seal Tape', quantity: '5', unit: 'PCS' },
        ],
    });
}
