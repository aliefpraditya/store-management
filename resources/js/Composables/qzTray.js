/**
 * Printing straight to a printer through QZ Tray, a small program on the cashier's computer that the page talks to over a
 * local websocket. The client library is loaded the first time it is needed, so a store that does not use QZ Tray never
 * downloads it. The page is not signed: QZ Tray asks once on the computer to trust it (tick "Remember this decision").
 */

import { thermalLayoutOf } from './thermalLayout';

/** @type {Promise<object>|null} */
let qzClient = null;

/** Pixels per millimetre of a CSS pixel (96 per inch), to turn the height of what is printed into the height of a roll page. */
const PX_PER_MM = 96 / 25.4;

function loadQz() {
    qzClient ??= import('qz-tray').then((module) => {
        const qz = module.default ?? module;

        qz.api.setPromiseType((resolver) => new Promise(resolver));

        return qz;
    });

    return qzClient;
}

/**
 * @returns {Promise<object>} the QZ Tray client, connected
 * @throws {Error} when QZ Tray is not running on this computer or the connection was refused
 */
async function connectedQz() {
    const qz = await loadQz();

    if (!qz.websocket.isActive()) {
        await qz.websocket.connect({ retries: 1, delay: 1 });
    }

    return qz;
}

/**
 * The printers this computer has, by the name the operating system knows them by.
 *
 * @returns {Promise<string[]>}
 * @throws {Error} when QZ Tray cannot be reached
 */
export async function listPrinters() {
    const qz = await connectedQz();
    const printers = await qz.printers.find();

    return Array.isArray(printers) ? printers : [printers];
}

/**
 * The paper of a print. A continuous form (a target with a height) is wider than it is tall, so QZ Tray, left to guess,
 * turns it to landscape and the content lands off the page, which prints blank; its orientation is therefore fixed to
 * portrait and its size marked custom because the driver may not list it. A roll has no fixed height and keeps QZ Tray's own choices.
 *
 * @param {{width_mm: number, height_mm: number|null}} target
 * @param {number} heightMm
 */
function paperOf(target, heightMm) {
    const isContinuousForm = target.height_mm !== null;

    return {
        size: { width: target.width_mm, height: heightMm, ...(isContinuousForm ? { custom: true } : {}) },
        ...(isContinuousForm ? { orientation: 'portrait' } : {}),
    };
}

/**
 * Prints an HTML page on a printer without opening the print dialog.
 *
 * @param {{name: string, width_mm: number, height_mm: number|null}} target the printer and the page it is loaded with; a roll has no height
 * @param {string} html the page to print; its styles must be inside it
 * @param {number} contentHeightPx how tall the page is on screen, which is the height of a roll page
 * @throws {Error} when QZ Tray cannot be reached or does not know the printer
 */
export async function printHtml(target, html, contentHeightPx) {
    const qz = await connectedQz();
    const heightMm = target.height_mm ?? Math.ceil(contentHeightPx / PX_PER_MM) + 4;
    const config = qz.configs.create(target.name, {
        units: 'mm',
        ...paperOf(target, heightMm),
        margins: 0,
        colorType: 'blackwhite',
    });

    await qz.print(config, [{ type: 'pixel', format: 'html', flavor: 'plain', data: html, options: { pageWidth: target.width_mm, pageHeight: heightMm } }]);
}

/**
 * The text of every style the document holds, so a page handed to QZ Tray as plain HTML keeps its look.
 *
 * @param {Document} document
 */
function stylesOf(document) {
    return [...document.styleSheets]
        .map((sheet) => {
            try {
                return [...sheet.cssRules].map((rule) => rule.cssText).join('\n');
            } catch {
                return '';
            }
        })
        .join('\n');
}

/**
 * Prints what a print page shows (without the grey surround of the screen) on a printer.
 *
 * @param {{name: string, width_mm: number, height_mm: number|null}} target
 * @param {HTMLElement} sheet the element holding the sheet
 * @throws {Error} when QZ Tray cannot be reached or does not know the printer
 */
export async function printSheetElement(target, sheet) {
    const html = `<!doctype html><html><head><meta charset="utf-8"><style>${stylesOf(sheet.ownerDocument)}\nbody { margin: 0; width: ${target.width_mm}mm; background: #fff; }</style></head><body>${sheet.innerHTML}</body></html>`;

    await printHtml(target, html, sheet.scrollHeight);
}

/**
 * The ways of putting a line at the left edge of the text area, one line each, so a printed test shows which of them the
 * renderer of QZ Tray follows. Each line names its own way; the one a line is at the left edge for is the one that works.
 */
const LEFT_ALIGNMENT_TRIALS = [
    '<div style="display:flex;justify-content:space-between"><span>REF kiri</span><span>kanan</span></div>',
    '<div style="text-align:left">A text-align left</div>',
    '<table width="100%" cellspacing="0" cellpadding="0"><tr><td align="left">B td align left</td></tr></table>',
    '<table cellspacing="0" cellpadding="0"><tr><td>C tabel sempit</td></tr></table>',
    '<table align="left" cellspacing="0" cellpadding="0"><tr><td>D tabel align</td></tr></table><br clear="all">',
    '<div style="float:left">E float left</div><div style="clear:both"></div>',
    '<div style="display:flex;justify-content:flex-start"><span>F flex-start</span></div>',
    '<div style="display:flex;justify-content:space-between"><span>G flex antara</span><span></span></div>',
    '<div style="width:40mm">H kotak 40mm</div>',
    '<pre style="margin:0;font:inherit">I pre</pre>',
    '<div style="position:relative;height:1.4em"><span style="position:absolute;left:0">J absolute</span></div>',
    '<div>K div biasa</div>',
    '<div style="text-align:center">M text-align tengah</div>',
];

/** Height on screen of one line of the test page, to size a roll page. */
const TEST_LINE_HEIGHT_PX = 26;

/**
 * A short test page, to see that the name given to a printer is the one the computer knows and that the paper is right. A
 * roll also prints the ways of aligning a line to the left, laid out within the same space a receipt is.
 *
 * @param {{name: string, width_mm: number, height_mm: number|null}} target
 * @param {string} paperLabel
 * @throws {Error} when QZ Tray cannot be reached or does not know the printer
 */
export async function printTestPage(target, paperLabel) {
    const escape = (text) => text.replace(/&/g, '&amp;').replace(/</g, '&lt;');
    const isRoll = target.height_mm === null;
    const trials = isRoll ? LEFT_ALIGNMENT_TRIALS : [];
    const layout = thermalLayoutOf(target.width_mm);
    const padding = isRoll ? `4mm ${layout.right}mm 4mm ${layout.left}mm` : '4mm';
    const trialsHtml = isRoll ? `<br><br>${trials.join('')}` : '';
    const html = `<!doctype html><html><head><meta charset="utf-8"><style>body { margin: 0; width: ${target.width_mm}mm; padding: ${padding}; box-sizing: border-box; background: #fff; font: 700 11pt 'Courier New', monospace; color: #000; }</style></head><body>TES CETAK<br>${escape(target.name)}<br>${escape(paperLabel)}<br>${new Date().toLocaleString('id-ID')}${trialsHtml}</body></html>`;

    await printHtml(target, html, 120 + trials.length * TEST_LINE_HEIGHT_PX);
}
