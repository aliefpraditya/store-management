/**
 * Prints a sheet in a window of its own, so the screen the user works on stays where it is.
 *
 * The window is opened before any waiting: browsers only allow a pop-up straight from a click, not after a request
 * has come back. It shows a short note until the sheet's address is known, then goes there; that page prints itself.
 *
 * @param {() => Promise<string>} prepareSheetUrl does the work first (e.g. counts the print on the server) and answers the sheet's address
 * @returns {Promise<void>}
 * @throws {Error} when the browser blocks the pop-up, or when preparing fails (the window is closed again)
 */
export async function printInNewWindow(prepareSheetUrl) {
    const printWindow = window.open('', '_blank');

    if (printWindow === null) {
        throw new Error('Jendela cetak diblokir browser. Izinkan pop-up untuk situs ini.');
    }

    printWindow.document.body.textContent = 'Menyiapkan cetakan…';

    try {
        printWindow.location.href = await prepareSheetUrl();
    } catch (error) {
        printWindow.close();

        throw error;
    }
}
