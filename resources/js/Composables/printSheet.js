import { Notify } from 'quasar';

/** The hidden frame of the sheet being printed; the next print replaces it. */
let printFrame = null;

/** Fired on the window once a sheet has been printed (or its print dialog closed), for a screen that wants its focus back. */
export const SHEET_PRINTED_EVENT = 'sheet-printed';

/** What a sheet in the hidden frame posts to its page when QZ Tray could not print it and the print dialog opens instead. */
export const SHEET_QZ_FALLBACK_MESSAGE = 'sheet-qz-fallback';

/** The frame cannot show anything to the cashier, so its page tells them why the print dialog opened. */
window.addEventListener('message', (event) => {
    if (event.origin !== window.location.origin || event.data?.type !== SHEET_QZ_FALLBACK_MESSAGE) {
        return;
    }

    Notify.create({
        type: 'warning',
        position: 'top',
        timeout: 8000,
        message: `QZ Tray tidak bisa mencetak (${event.data.message}). Cetak lewat dialog browser.`,
    });
});

/**
 * The print dialog leaves the keyboard focus in the hidden frame, so the frame goes and the page takes the focus back
 * before the screen is told.
 *
 * @param {HTMLIFrameElement} frame
 */
function finishPrint(frame) {
    frame.remove();
    window.focus();
    window.dispatchEvent(new Event(SHEET_PRINTED_EVENT));
}

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
    frame.addEventListener('load', () => frame.contentWindow?.addEventListener('afterprint', () => finishPrint(frame)), { once: true });

    document.body.appendChild(frame);
    printFrame = frame;
}
