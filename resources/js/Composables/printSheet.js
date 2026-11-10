/** The hidden frame of the sheet being printed; the next print replaces it. */
let printFrame = null;

/**
 * Prints a sheet from a hidden frame of the page the user is on, so the screen stays where it is.
 *
 * A window of its own was tried first and dropped: after the user switched to its tab and back, Chromium on Windows
 * left the first tab deaf to typed digits and letters until Alt was pressed once.
 *
 * The sheet's page prints itself once loaded. The frame is not 0×0 and not `display: none`, because Chromium prints
 * such a frame blank.
 *
 * @param {() => Promise<string>|string} prepareSheetUrl does the work first (e.g. counts the print on the server) and answers the sheet's address
 * @returns {Promise<void>}
 * @throws {Error} when preparing fails; nothing is printed
 */
export async function printSheet(prepareSheetUrl) {
    const sheetUrl = await prepareSheetUrl();

    printFrame?.remove();

    const frame = document.createElement('iframe');

    frame.src = sheetUrl;
    frame.tabIndex = -1;
    frame.setAttribute('aria-hidden', 'true');
    frame.style.cssText = 'position: fixed; right: 0; bottom: 0; width: 1px; height: 1px; border: 0; opacity: 0; pointer-events: none;';
    frame.addEventListener('load', () => frame.contentWindow?.addEventListener('afterprint', () => frame.remove()), { once: true });

    document.body.appendChild(frame);
    printFrame = frame;
}
