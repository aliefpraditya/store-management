/**
 * Plain text for a printer that prints its own characters (ESC/POS on a receipt printer, ESC/P on a form printer): lines of a
 * fixed number of columns, placed at the left, the middle or the right. What a document does with the lines, and the commands
 * around them, belongs to that document's own module.
 */

/** The accents that NFD splits off a letter (U+0300 to U+036F), built from their codes so the source stays plain ASCII. */
const COMBINING_MARKS = new RegExp(`[${String.fromCharCode(0x300)}-${String.fromCharCode(0x36f)}]`, 'g');

/**
 * The font of these printers holds plain ASCII; an accent is dropped and any other character becomes a question mark.
 *
 * @param {string} text
 */
export function printable(text) {
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
export function wrap(text, columns) {
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

/** The lines of a document, each placed at the left, the middle or the right of the printer's columns. */
export class TextLines {
    /**
     * @param {number} columns
     * @param {{on: string, off: string}} bold the commands that turn bold on and off, which differ between printer languages
     */
    constructor(columns, bold) {
        this.columns = columns;
        this.bold = bold;
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
     * @param {string[]} lines
     * @param {boolean} isBold
     */
    #push(lines, isBold) {
        this.lines.push(...lines.map((line) => (isBold ? `${this.bold.on}${line}${this.bold.off}` : line)));
    }
}
