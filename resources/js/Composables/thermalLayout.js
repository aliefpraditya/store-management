/**
 * Where text may sit on a thermal or dot matrix roll, per roll width in millimetres.
 *
 * A printer cannot print edge to edge, and the window it does print is not always centred on the roll: a 76 mm dot matrix
 * roll (Epson TM-U220) prints only about 62 mm, starting about 9 mm from the left edge, and its centred letterhead prints
 * about 6 mm to the right of the rest of the sheet. The 76 mm figures were measured from printed receipts; the other widths
 * keep the default until a print shows otherwise.
 *
 * @typedef {{left: number, right: number, letterheadShift: number}} ThermalLayout space kept free left and right of the text, and how far the letterhead is moved left, in millimetres
 */

/** @type {ThermalLayout} */
const DEFAULT_LAYOUT = { left: 4, right: 4, letterheadShift: 0 };

/** @type {Record<number, ThermalLayout>} */
const LAYOUT_BY_WIDTH = { 76: { left: 9, right: 6, letterheadShift: 6 } };

/**
 * @param {number} widthMm the width of the roll
 * @returns {ThermalLayout}
 */
export function thermalLayoutOf(widthMm) {
    return LAYOUT_BY_WIDTH[widthMm] ?? DEFAULT_LAYOUT;
}
